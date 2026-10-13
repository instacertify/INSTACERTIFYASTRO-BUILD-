<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageConversionApis;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Support\ConversionApis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ConversionApisTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT = '3f1c2a9e-7b4d-4c1e-9f0a-1b2c3d4e5f60';

    private function configure(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageConversionApis::class)
            ->set('data.ga4.enabled', true)
            ->set('data.ga4.measurementId', 'g-f1npmwjc4t')
            ->set('data.ga4.secret', 'ga-secret-123')
            ->set('data.uet.enabled', true)
            ->set('data.uet.tagId', '123456789')
            ->set('data.uet.token', 'uet-token-abc')
            ->set('data.linkedin.enabled', true)
            ->set('data.linkedin.conversionId', '12345678')
            ->set('data.linkedin.token', 'li-token-xyz')
            ->call('save')
            ->assertHasNoErrors();
    }

    private function enquiry(array $tracking): void
    {
        $this->postJson('/api/v1/leads', [
            'type' => 'quote', 'name' => 'Asha Verma', 'email' => 'A.sha+ads@Example.com', 'phone' => '98765 43210',
            'company' => 'Volt Devices', 'consent' => true, 'tracking' => $tracking,
        ])->assertCreated();
    }

    private function fullContext(array $overrides = []): array
    {
        return [
            'event_id' => self::EVENT,
            'page_url' => 'https://instacertify.com/contact',
            'ad_consent' => true,
            'ga_client_id' => '1234567890.1700000000',
            'ga_session_id' => '1791867813',
            'uet_vid' => '2a3f7b00c6c411f1b311a30cb52090b1',
            'msclkid' => '_uetdd4afcccb1c94a4cad9544dd7e5006ab',
            'li_fat_id' => 'df5gf5-gh6t7-ph4j7h-fgf6n1',
            ...$overrides,
        ];
    }

    public function test_tokens_are_stored_encrypted_and_never_published(): void
    {
        $this->configure();

        $stored = Setting::find('conversions')->value;
        $this->assertNotSame('uet-token-abc', $stored['uet']['token']);
        $this->assertSame('uet-token-abc', Crypt::decryptString($stored['uet']['token']));
        $this->assertSame('G-F1NPMWJC4T', $stored['ga4']['measurementId']);

        Livewire::test(ManageConversionApis::class)
            ->assertSet('data.uet.token', null)
            ->set('data.uet.tagId', '987654321')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('uet-token-abc', ConversionApis::secret('uet'), 'Leaving the token empty keeps the saved one');

        $site = $this->getJson('/api/v1/site')
            ->assertJsonPath('data.integrations.serverSide', ['ga4' => 'G-F1NPMWJC4T', 'uet' => true, 'linkedin' => true]);
        foreach (['ga-secret-123', 'uet-token-abc', 'li-token-xyz', $stored['uet']['token']] as $secret) {
            $this->assertStringNotContainsString($secret, $site->getContent());
        }
    }

    public function test_switching_a_platform_on_needs_its_id_and_token(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageConversionApis::class)
            ->set('data.linkedin.enabled', true)
            ->set('data.uet.enabled', true)
            ->set('data.uet.tagId', 'abc')
            ->set('data.uet.token', 'x')
            ->call('save')
            ->assertHasErrors(['data.linkedin.conversionId', 'data.linkedin.token', 'data.uet.tagId']);
    }

    public function test_only_admins_open_the_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get(ManageConversionApis::getUrl())->assertForbidden();
    }

    public function test_an_enquiry_is_reported_to_each_platform_once_with_the_browser_event_id(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(null, 204)]);

        $this->enquiry($this->fullContext());

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://www.google-analytics.com/mp/collect?measurement_id=G-F1NPMWJC4T&api_secret=ga-secret-123')
            && $r['client_id'] === '1234567890.1700000000'
            && $r['events'][0]['name'] === 'generate_lead'
            && $r['events'][0]['params']['session_id'] === '1791867813'
            && $r['consent']['ad_user_data'] === 'GRANTED');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://capi.uet.microsoft.com/v1/123456789/events'
            && $r->hasHeader('Authorization', 'Bearer uet-token-abc')
            && $r['data'][0]['eventId'] === self::EVENT
            && $r['data'][0]['eventName'] === 'submit_lead_form'
            && $r['data'][0]['userData']['em'] === hash('sha256', 'asha@example.com')
            && $r['data'][0]['userData']['ph'] === hash('sha256', '+919876543210')
            && $r['data'][0]['userData']['msclkid'] === 'dd4afccc-b1c9-4a4c-ad95-44dd7e5006ab'
            && $r['data'][0]['userData']['anonymousId'] === '2a3f7b00c6c411f1b311a30cb52090b1');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.linkedin.com/rest/conversionEvents'
            && $r->hasHeader('Authorization', 'Bearer li-token-xyz')
            && $r->hasHeader('X-Restli-Protocol-Version', '2.0.0')
            && preg_match('/^\d{6}$/', $r->header('LinkedIn-Version')[0])
            && $r['conversion'] === 'urn:lla:llaPartnerConversion:12345678'
            && $r['eventId'] === self::EVENT
            && $r['user']['userIds'] === [
                ['idType' => 'SHA256_EMAIL', 'idValue' => hash('sha256', 'a.sha+ads@example.com')],
                ['idType' => 'LINKEDIN_FIRST_PARTY_ADS_TRACKING_UUID', 'idValue' => 'df5gf5-gh6t7-ph4j7h-fgf6n1'],
            ]
            && $r['user']['userInfo'] === ['firstName' => 'Asha', 'lastName' => 'Verma', 'companyName' => 'Volt Devices']);

        $this->assertNull(ConversionApis::lastResult('uet')['error']);
    }

    public function test_consent_decides_which_platforms_hear_about_the_enquiry(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(null, 204)]);

        $this->enquiry($this->fullContext(['ad_consent' => false]));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'google-analytics.com') && $r['consent']['ad_user_data'] === 'DENIED');

        $this->enquiry(['event_id' => self::EVENT, 'ad_consent' => false]);
        Http::assertSentCount(1);
    }

    public function test_bad_visitor_ids_are_dropped_without_rejecting_the_enquiry(): void
    {
        $this->configure();
        Http::fake(['*' => Http::response(null, 204)]);

        $this->enquiry($this->fullContext(['ga_client_id' => '<script>', 'msclkid' => 'x', 'page_url' => 'javascript:alert(1)', 'extra' => 'ignored']));

        $this->assertSame(1, Lead::count());
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'uet') && ! isset($r['data'][0]['userData']['msclkid']) && ! isset($r['data'][0]['eventSourceUrl']));
    }

    public function test_a_refused_token_is_shown_and_does_not_break_the_enquiry(): void
    {
        $this->configure();
        Http::fake(['capi.uet.microsoft.com/*' => Http::response(['error' => 'Unauthorized'], 401), '*' => Http::response(null, 204)]);

        $this->enquiry($this->fullContext());

        $this->assertStringStartsWith('401', ConversionApis::lastResult('uet')['error']);
        $this->assertNull(ConversionApis::lastResult('linkedin')['error']);
        Livewire::test(ManageConversionApis::class)->assertSee('Last enquiry NOT accepted');
    }

    public function test_connection_check_tells_an_accepted_token_from_a_refused_one(): void
    {
        $this->configure();
        Http::fake([
            'capi.uet.microsoft.com/*' => Http::response(['error' => 'data is empty'], 400),
            'api.linkedin.com/*' => Http::response(['message' => 'Unauthorized'], 401),
            'www.google-analytics.com/debug/*' => Http::response(['validationMessages' => []]),
        ]);

        $this->assertTrue(ConversionApis::check('uet')['ok']);
        $this->assertFalse(ConversionApis::check('linkedin')['ok']);
        $this->assertTrue(ConversionApis::check('ga4')['ok']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/mp/collect') && ! str_contains($r->url(), '/debug/'));
    }
}
