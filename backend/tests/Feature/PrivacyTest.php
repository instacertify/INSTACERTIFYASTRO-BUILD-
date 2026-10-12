<?php

namespace Tests\Feature;

use App\Filament\Resources\ConsentLogs\Pages\ListConsentLogs;
use App\Models\ConsentLog;
use App\Models\Lead;
use App\Models\Page;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\Privacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function consent(array $overrides = []): array
    {
        return [
            'consent_id' => (string) Str::uuid(),
            'action' => 'custom',
            'choices' => ['preferences' => true, 'analytics' => false, 'marketing' => false],
            'version' => Privacy::version(),
            'page' => '/contact',
            'language' => 'hi',
            ...$overrides,
        ];
    }

    public function test_consent_choice_is_logged_with_ip(): void
    {
        $payload = $this->consent();

        $this->postJson('/api/v1/consents', $payload, ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Firefox'])
            ->assertCreated()
            ->assertJsonPath('data.currentVersion', Privacy::version())
            ->assertDontSee('203.0.113.9');

        $log = ConsentLog::sole();
        $this->assertSame($payload['consent_id'], $log->consent_id);
        $this->assertSame(['necessary' => true, 'preferences' => true, 'analytics' => false, 'marketing' => false], $log->choices);
        $this->assertSame('hi', $log->language);
        $this->assertSame('203.0.113.9', $log->ip);
        $this->assertSame('Firefox', $log->user_agent);
        $this->assertSame(Privacy::hashIp('203.0.113.9'), $log->ip_hash);
        $this->assertStringNotContainsString('203.0.113.9', json_encode($log->toArray()));
    }

    public function test_admin_sees_consent_ip(): void
    {
        $log = ConsentLog::create([...$this->consentRow(), 'ip' => '203.0.113.9']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ListConsentLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('203.0.113.9')
            ->searchTable('203.0.113.9')
            ->assertCanSeeTableRecords([$log]);
    }

    public function test_policies_describe_ip_collection(): void
    {
        $privacy = Page::create(['path' => 'privacy-policy', 'title' => 'Privacy policy', 'template' => 'policy', 'content' => '<p><strong>Last Updated:</strong> 3 November 2025 cookies and analytics info (internal CRM). <strong>8. Cookies</strong> Users can manage or disable cookies through their browser settings or opt-out tools.</p>']);
        $cookies = Page::create(['path' => 'cookie-policy', 'title' => 'Cookie policy', 'template' => 'policy', 'content' => '<p>Edited by hand.</p>']);
        $migration = require database_path('migrations/2026_10_09_030100_add_ip_collection_to_privacy_and_cookie_policies.php');

        $migration->up();
        $migration->up();

        $content = $privacy->fresh()->content;
        $this->assertStringContainsString('Last Updated:</strong> 8 October 2026', $content);
        $this->assertStringContainsString('internal CRM). <strong>IP address with enquiries:</strong>', $content);
        $this->assertStringContainsString('deleted automatically after 180 days', $content);
        $this->assertStringContainsString('opt-out tools. When you make a choice in our cookie notice', $content);
        $this->assertStringContainsString('<p>IP addresses recorded with enquiries are deleted after 180 days and cookie consent records, including IP addresses, after 24 months.</p>', $content);
        $this->assertSame(1, substr_count($content, 'IP address with enquiries'));

        $content = $cookies->fresh()->content;
        $this->assertStringStartsWith('<p>Edited by hand.</p><p>Each consent record also holds the language, your IP address and your browser type.', $content);
        $this->assertStringNotContainsString('hashed', $content);
        $this->assertSame(1, substr_count($content, 'your IP address and your browser type'));
    }

    public function test_consent_payload_is_validated(): void
    {
        $this->postJson('/api/v1/consents', $this->consent(['consent_id' => 'nope', 'action' => 'sell', 'choices' => ['analytics' => 'maybe']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['consent_id', 'action', 'choices.analytics', 'choices.preferences']);
    }

    public function test_consent_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 20) as $i) {
            $this->postJson('/api/v1/consents', $this->consent())->assertCreated();
        }

        $this->postJson('/api/v1/consents', $this->consent())->assertTooManyRequests();
    }

    public function test_site_settings_expose_privacy_contact(): void
    {
        $this->getJson('/api/v1/site')
            ->assertOk()
            ->assertJsonPath('data.privacy.version', Privacy::version())
            ->assertJsonPath('data.privacy.grievanceEmail', Privacy::DEFAULTS['grievanceEmail'])
            ->assertJsonPath('data.privacy.consentLogMonths', Privacy::DEFAULTS['consentLogMonths'])
            ->assertJsonPath('data.privacy.ipDays', Privacy::DEFAULTS['ipDays'])
            ->assertJsonMissingPath('data.privacy.leadMonths');
    }

    public function test_prune_applies_retention_periods(): void
    {
        Queue::fake();
        $oldConsent = ConsentLog::create($this->consentRow());
        $oldConsent->forceFill(['created_at' => now()->subMonths(25)])->save();
        $recentConsent = ConsentLog::create($this->consentRow());

        $closed = Lead::create(['name' => 'Asha', 'email' => 'asha@example.com', 'phone' => '+919876543210', 'status' => 'lost', 'ip' => '203.0.113.9']);
        $open = Lead::create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'status' => 'new', 'ip' => '203.0.113.10']);
        Lead::whereKey([$closed->id, $open->id])->toBase()->update(['created_at' => now()->subMonths(61), 'updated_at' => now()->subMonths(61)]);
        $this->assertTrue(Activity::where('subject_id', $closed->id)->exists());

        $pending = Subscriber::create(['email' => 'old@example.com', 'status' => 'pending']);
        Subscriber::whereKey($pending->id)->update(['created_at' => now()->subDays(31)]);

        $this->artisan('privacy:prune', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($closed->fresh()->anonymised_at);

        $this->artisan('privacy:prune')->assertSuccessful();

        $this->assertModelMissing($oldConsent);
        $this->assertModelExists($recentConsent);

        $closed->refresh();
        $this->assertSame('Anonymised', $closed->name);
        $this->assertNull($closed->email);
        $this->assertNull($closed->phone);
        $this->assertNull($closed->ip);
        $this->assertNotNull($closed->anonymised_at);
        $this->assertFalse(Activity::where('subject_type', $closed->getMorphClass())->where('subject_id', $closed->id)->exists());

        $open->refresh();
        $this->assertSame('ravi@example.com', $open->email);
        $this->assertNull($open->ip);

        $this->assertModelMissing($pending);
    }

    private function consentRow(): array
    {
        return [
            'consent_id' => (string) Str::uuid(),
            'action' => 'accept_all',
            'choices' => ['necessary' => true, 'preferences' => true, 'analytics' => true, 'marketing' => true],
            'policy_version' => Privacy::version(),
        ];
    }
}
