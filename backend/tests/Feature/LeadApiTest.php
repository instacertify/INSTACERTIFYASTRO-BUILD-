<?php

namespace Tests\Feature;

use App\Filament\Resources\Leads\Pages\EditLead;
use App\Jobs\ProcessNewLead;
use App\Jobs\SyncLeadToWebhook;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use App\Support\Privacy;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LeadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'type' => 'quote',
            'name' => 'Asha Verma',
            'email' => 'Asha@Example.com',
            'phone' => '+91 98765 43210',
            'company' => 'Volt Devices',
            'service_path' => 'certification/india/bis-crs-mark-certification',
            'product' => 'Power bank 10000 mAh',
            'timeline' => 'urgent',
            'source_page' => '/certification/india/bis-crs-mark-certification',
            'utm_source' => 'google',
            'consent' => true,
            ...$overrides,
        ];
    }

    public function test_lead_is_stored_and_queued(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'message']]);

        $lead = Lead::sole();
        $this->assertSame('asha@example.com', $lead->email);
        $this->assertSame('+919876543210', $lead->phone);
        $this->assertSame('new', $lead->status);
        $this->assertSame(Privacy::version(), $lead->consent_version);
        $this->assertNotNull($lead->consented_at);
        $this->assertFalse($lead->marketing_opt_in);
        Queue::assertPushed(ProcessNewLead::class);
    }

    public function test_lead_records_the_visitors_ip(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload(), ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'])->assertCreated();
        $this->assertSame('203.0.113.9', Lead::sole()->ip, 'Forwarded headers from untrusted clients are ignored');

        config(['trustedproxy.proxies' => '10.0.0.1']);
        $this->postJson('/api/v1/leads', $this->payload(), ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'])->assertCreated();
        $this->assertSame('198.51.100.7', Lead::latest('id')->first()->ip);
    }

    public function test_admin_sees_lead_ip_and_notes(): void
    {
        $lead = Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'status' => 'new', 'ip' => '203.0.113.9', 'user_agent' => 'Firefox', 'notes' => 'Called twice']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditLead::class, ['record' => $lead->getRouteKey()])
            ->assertSchemaStateSet(['ip' => '203.0.113.9', 'user_agent' => 'Firefox', 'notes' => 'Called twice'])
            ->fillForm(['status' => 'contacted'])
            ->call('save')
            ->assertHasNoFormErrors();

        $lead->refresh();
        $this->assertSame('contacted', $lead->status);
        $this->assertSame('Called twice', $lead->notes);
        $this->assertSame('203.0.113.9', $lead->ip);
    }

    public function test_consent_is_required_and_marketing_is_separate(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload(['consent' => false]))
            ->assertJsonValidationErrors('consent');

        $this->postJson('/api/v1/leads', $this->payload(['marketing_opt_in' => true]))->assertCreated();
        $this->assertTrue(Lead::sole()->marketing_opt_in);
    }

    public function test_email_or_phone_is_required(): void
    {
        $this->postJson('/api/v1/leads', $this->payload(['email' => null, 'phone' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->postJson('/api/v1/leads', $this->payload(['website' => 'http://spam.test']))
            ->assertUnprocessable();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_contact_form_records_what_the_visitor_needs(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload(['requirement' => 'inspection', 'service_path' => null]))->assertCreated();
        $this->assertSame('inspection', Lead::sole()->requirement);

        $this->postJson('/api/v1/leads', $this->payload(['requirement' => 'audit']))
            ->assertJsonValidationErrors('requirement');
    }

    public function test_a_removed_service_or_long_tracking_data_never_loses_the_enquiry(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload([
            'service_path' => 'made/up',
            'referrer' => 'https://www.google.com/search?q='.str_repeat('bis+crs+registration+', 30),
            'utm_campaign' => str_repeat('campaign-', 40),
            'gclid' => str_repeat('Cj0KCQ', 60),
        ]))->assertCreated();

        $lead = Lead::sole();
        $this->assertNull($lead->service_path);
        $this->assertSame(255, strlen($lead->referrer));
        $this->assertSame(150, strlen($lead->utm_campaign));
        $this->assertSame(255, strlen($lead->gclid));
        Queue::assertPushed(ProcessNewLead::class);
    }

    public function test_every_form_on_the_site_is_accepted(): void
    {
        Queue::fake();
        $base = ['name' => 'Asha Verma', 'consent' => '1', 'source_page' => '/x', 'website' => ''];

        $forms = [
            'contact page' => ['type' => 'quote', 'requirement' => 'certification', 'service_path' => '', 'product' => 'Charger', 'manufacturer_type' => 'foreign', 'timeline' => '1-3-months', 'company' => 'Volt', 'phone' => '+91 98765 43210', 'email' => 'a@example.com', 'message' => 'Hi', 'marketing_opt_in' => false],
            'service page' => ['type' => 'consultation', 'service_path' => 'certification/india/bis-crs-mark-certification', 'phone' => '+91 98765 43210', 'email' => 'a@example.com', 'product' => 'Speaker'],
            'popup, phone only' => ['type' => 'consultation', 'phone' => '9876543210'],
            'BIS fee finder' => ['type' => 'consultation', 'requirement' => 'certification', 'product' => 'Cables – IS 694 (ISI)', 'service_path' => 'certification/india/bis-crs-mark-certification', 'manufacturer_type' => 'indian', 'message' => 'BIS fee finder: Cables', 'scale' => 'micro', 'phone' => '+91 98765 43210'],
        ];

        foreach ($forms as $fields) {
            $fields = array_filter([...$base, ...$fields], fn ($v) => $v !== '');
            $this->postJson('/api/v1/leads', $fields)->assertCreated();
        }

        $this->assertSame(count($forms), Lead::count());
        Queue::assertPushed(ProcessNewLead::class, count($forms));
    }

    public function test_leads_are_rate_limited(): void
    {
        Queue::fake();

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/v1/leads', $this->payload())->assertCreated();
        }

        $this->postJson('/api/v1/leads', $this->payload())->assertTooManyRequests();
    }

    public function test_job_notifies_team_and_calls_webhook(): void
    {
        Notification::fake();
        Http::fake(['hooks.test/*' => Http::response(['ok' => true])]);
        config([
            'instacertify.leads.webhook_url' => 'https://hooks.test/lead',
            'instacertify.leads.webhook_secret' => 'secret',
        ]);

        $lead = Lead::create($this->payload(['email' => 'asha@example.com', 'phone' => '+919876543210']));
        (new ProcessNewLead($lead))->handle();
        (new SyncLeadToWebhook($lead))->handle();

        Notification::assertSentOnDemand(NewLeadNotification::class);
        Http::assertSent(fn ($request) => $request['event'] === 'lead.created'
            && $request['lead']['service_title'] === 'BIS CRS Registration'
            && $request->hasHeader('X-Instacertify-Signature'));
        $this->assertNotNull($lead->fresh()->synced_at);
    }

    public function test_webhook_is_queued_separately_so_retries_never_resend_the_email(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();
        Queue::assertPushed(ProcessNewLead::class, 1);
        Queue::assertNotPushed(SyncLeadToWebhook::class);

        config(['instacertify.leads.webhook_url' => 'https://hooks.test/lead']);
        $this->postJson('/api/v1/leads', $this->payload())->assertCreated();
        Queue::assertPushed(ProcessNewLead::class, 2);
        Queue::assertPushed(SyncLeadToWebhook::class, 1);
    }
}
