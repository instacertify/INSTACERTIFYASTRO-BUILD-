<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageContactBoxes;
use App\Filament\Pages\ManageSite;
use App\Models\Setting;
use App\Models\User;
use App\Support\ContactBoxes;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ContactBoxesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(ContentSeeder::class);
    }

    public function test_the_website_gets_the_default_wording_until_it_is_changed(): void
    {
        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('data.contactBoxes.cta.heading', ContactBoxes::defaults()['cta']['heading'])
            ->assertJsonPath('data.contactBoxes.contactPage.points', ContactBoxes::defaults()['contactPage']['points'])
            ->assertJsonPath('data.contactBoxes.thankYou.steps.0.icon', 'mark_email_read');
    }

    public function test_an_editor_can_reword_the_boxes_without_touching_other_settings(): void
    {
        $site = Setting::find('site');
        $site->value = [...$site->value, 'popup' => ['enabled' => true, 'delay' => 30, 'title' => 'Old title'], 'leadForm' => ['privacyNote' => 'No spam']];
        $site->save();
        $before = Setting::get('site');
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        Livewire::test(ManageContactBoxes::class)
            ->assertSet('data.popup.title', 'Old title')
            ->assertSet('data.contactBoxes.cta.heading', null)
            ->assertCount('data.contactBoxes.cta.points', count(ContactBoxes::defaults()['cta']['points']))
            ->set('data.contactBoxes.cta.heading', 'Need help choosing?')
            ->set('data.contactBoxes.cta.points', ['item-1' => ['text' => 'Written list of approvals']])
            ->set('data.contactBoxes.contactPage.points', [])
            ->set('data.contactBoxes.expert.chat', '  ')
            ->set('data.popup.title', 'Get your quote')
            ->set('data.leadForm.title', 'Free check')
            ->call('save')
            ->assertHasNoErrors();

        $saved = Setting::get('site');
        $this->assertSame(['enabled' => true, 'delay' => 30, 'title' => 'Get your quote'], Arr::only($saved['popup'], ['enabled', 'delay', 'title']));
        $this->assertSame('No spam', $saved['leadForm']['privacyNote']);
        $this->assertSame('Free check', $saved['leadForm']['title']);
        $this->assertSame($before['contact'], $saved['contact']);
        $this->assertSame($before['nav'], $saved['nav']);

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.contactBoxes.cta.heading', 'Need help choosing?')
            ->assertJsonPath('data.contactBoxes.cta.points', ['Written list of approvals'])
            ->assertJsonPath('data.contactBoxes.cta.text', ContactBoxes::defaults()['cta']['text'])
            ->assertJsonPath('data.contactBoxes.contactPage.points', [])
            ->assertJsonPath('data.contactBoxes.expert.chat', 'Chat')
            ->assertJsonPath('data.popup.title', 'Get your quote');
    }

    public function test_consent_text_must_keep_the_privacy_policy_link(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageContactBoxes::class)
            ->set('data.contactBoxes.form.consent', 'I agree to be contacted.')
            ->call('save')
            ->assertHasErrors(['data.contactBoxes.form.consent']);

        Livewire::test(ManageContactBoxes::class)
            ->set('data.contactBoxes.form.consent', 'I agree to be contacted, see the privacy policy.')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_site_settings_keep_the_popup_wording(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageContactBoxes::class)->set('data.popup.title', 'Get your quote')->call('save')->assertHasNoErrors();

        Livewire::test(ManageSite::class)->set('data.popup.delay', 40)->call('save')->assertHasNoErrors();

        $popup = Setting::get('site')['popup'];
        $this->assertSame('Get your quote', $popup['title']);
        $this->assertEquals(40, $popup['delay']);
    }
}
