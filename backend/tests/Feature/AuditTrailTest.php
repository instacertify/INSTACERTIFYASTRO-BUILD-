<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSite;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Priya', 'role' => 'admin']);
    }

    public function test_site_settings_changes_are_logged_field_by_field(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ManageSite::class)->call('save');
        $old = Setting::get('site')['tagline'] ?? null;

        Livewire::test(ManageSite::class)
            ->fillForm(['tagline' => 'Certification made simple'])
            ->call('save')
            ->assertHasNoErrors();

        $log = Activity::where('log_name', 'settings')->latest('id')->first();
        $this->assertSame('Updated Site settings', $log->description);
        $this->assertTrue($log->causer->is($this->admin));
        $this->assertSame(['tagline' => $old], $log->properties['old']);
        $this->assertSame(['tagline' => 'Certification made simple'], $log->properties['attributes']);
        $this->assertSame('127.0.0.1', $log->properties['ip']);
    }

    public function test_menu_and_home_page_saves_are_named_after_their_admin_page(): void
    {
        $this->actingAs($this->admin);
        $site = Setting::find('site');
        $site->value = [...$site->value, 'nav' => [['label' => 'Contact', 'href' => '/contact']]];
        $site->save();
        Setting::create(['key' => 'home', 'value' => ['title' => 'Hello']]);
        $site->save();

        $this->assertSame(['Updated Header menu', 'Created Home page'], Activity::where('log_name', 'settings')->whereMorphedTo('causer', $this->admin)->orderBy('id')->pluck('description')->all());
    }

    public function test_content_edits_record_who_what_and_from_where(): void
    {
        $page = Page::create(['path' => 'audit-test', 'title' => 'Audit test page', 'content' => '<p>Old</p>'])->fresh();
        $this->actingAs($this->admin);

        $page->update(['content' => '<p>New</p>']);

        $log = Activity::where('subject_type', $page->getMorphClass())->where('subject_id', $page->id)->latest('id')->first();
        $this->assertSame('updated', $log->event);
        $this->assertTrue($log->causer->is($this->admin));
        $this->assertSame('<p>Old</p>', $log->properties['old']['content']);
        $this->assertSame('<p>New</p>', $log->properties['attributes']['content']);
        $this->assertSame('127.0.0.1', $log->properties['ip']);

        $this->get(ActivityResource::getUrl())
            ->assertOk()
            ->assertSee('Priya')
            ->assertSee('Audit test page')
            ->assertSee('Fields changed');
    }

    public function test_sign_ins_are_logged(): void
    {
        $this->assertFalse(Auth::attempt(['email' => $this->admin->email, 'password' => 'wrong-password']));
        $this->assertTrue(Auth::attempt(['email' => $this->admin->email, 'password' => 'password']));
        Auth::logout();

        $logs = Activity::where('log_name', 'auth')->orderBy('id')->get();
        $this->assertSame(['login_failed', 'login', 'logout'], $logs->pluck('event')->all());
        $this->assertSame($this->admin->email, $logs[0]->properties['email']);
        $this->assertNull($logs[0]->causer_id);
        $this->assertTrue($logs[1]->causer->is($this->admin));
        $this->assertSame('127.0.0.1', $logs[1]->properties['ip']);
    }

    public function test_password_changes_are_logged_without_the_password(): void
    {
        $this->actingAs($this->admin);
        $user = User::factory()->create(['role' => 'editor']);

        $user->update(['password' => 'a-new-long-password', 'role' => 'sales']);

        $logs = Activity::where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)->get();
        $this->assertEqualsCanonicalizing(['created', 'updated', 'password_changed'], $logs->pluck('event')->all());
        $this->assertStringNotContainsString('password":', $logs->pluck('properties')->toJson());
        $this->assertSame(['role' => 'sales'], $logs->firstWhere('event', 'updated')->properties['attributes']);
    }

    public function test_csv_exports_are_logged(): void
    {
        Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'status' => 'new']);
        $this->actingAs($this->admin);

        Livewire::test(ListLeads::class, ['activeTab' => 'all'])->callAction('export');

        $log = Activity::where('event', 'exported')->sole();
        $this->assertSame('Exported 1 leads to CSV', $log->description);
        $this->assertTrue($log->causer->is($this->admin));
    }

    public function test_visitor_details_stay_out_of_the_log(): void
    {
        Queue::fake();

        Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'status' => 'new', 'ip' => '203.0.113.9', 'user_agent' => 'Firefox']);
        $this->postJson('/api/v1/subscribers', ['email' => 'reader@example.com'], ['REMOTE_ADDR' => '203.0.113.10']);

        $this->assertStringNotContainsString('203.0.113', Activity::pluck('properties')->toJson());
        $this->assertFalse(Activity::where('subject_type', (new Subscriber)->getMorphClass())->exists());

        $this->actingAs($this->admin);
        Subscriber::sole()->delete();
        $this->assertTrue(Activity::where('subject_type', (new Subscriber)->getMorphClass())->where('event', 'deleted')->exists());
    }
}
