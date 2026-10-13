<?php

namespace Tests\Feature;

use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationApiTest extends TestCase
{
    use RefreshDatabase;

    private function lead(array $attributes = []): Lead
    {
        return Lead::create([
            'type' => 'quote', 'status' => 'new', 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone' => '+919876543210',
            'consent' => true, 'ip' => '203.0.113.9', 'user_agent' => 'Mozilla/5.0', 'notes' => 'Internal: call after 5pm',
            ...$attributes,
        ]);
    }

    private function key(string $role = 'admin', array $abilities = ['leads:read'], $expiresAt = null): string
    {
        return User::factory()->create(['role' => $role])->createToken('CRM', $abilities, $expiresAt)->plainTextToken;
    }

    private function api(string $key, string $uri = '/api/v1/integrations/leads')
    {
        // The test app is reused between requests; forget the user the previous request signed in.
        $this->app['auth']->forgetGuards();

        return $this->withToken($key)->getJson($uri);
    }

    public function test_enquiries_need_a_key(): void
    {
        $this->getJson('/api/v1/integrations/leads')->assertUnauthorized();
        $this->api('1|not-a-real-key')->assertUnauthorized();
    }

    public function test_a_key_reads_enquiries_without_private_fields(): void
    {
        Queue::fake();
        $this->travel(-3)->days();
        $old = $this->lead();
        $this->travelBack();
        $new = $this->lead(['name' => 'Ravi Kumar', 'status' => 'contacted', 'utm_source' => 'google']);
        $key = $this->key();

        $response = $this->api($key)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.0.id', $new->uuid)
            ->assertJsonPath('data.0.utm.source', 'google')
            ->assertJsonPath('data.1.id', $old->uuid)
            ->assertJsonPath('meta.total', 2);
        foreach (['203.0.113.9', 'Mozilla', 'Internal: call'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }

        $this->api($key, '/api/v1/integrations/leads?status=contacted')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Ravi Kumar');
        $this->api($key, '/api/v1/integrations/leads?created_after='.urlencode(now()->subDay()->toIso8601String()))->assertJsonCount(1, 'data');
        $this->api($key, "/api/v1/integrations/leads/{$old->uuid}")->assertOk()->assertJsonPath('data.name', 'Asha Verma');
        $this->api($key, '/api/v1/integrations/leads?per_page=500')->assertUnprocessable();
        $this->assertNotNull(PersonalAccessToken::first()->last_used_at);
    }

    public function test_keys_stop_working_when_revoked_expired_or_not_allowed(): void
    {
        $this->api($this->key('editor'))->assertForbidden();
        $this->api($this->key('admin', ['other']))->assertForbidden();
        $this->api($this->key('sales'))->assertOk();
        $this->api($this->key('admin', ['leads:read'], now()->subMinute()))->assertUnauthorized();

        $key = $this->key();
        PersonalAccessToken::findToken($key)->delete();
        $this->api($key)->assertUnauthorized();
    }

    public function test_admins_create_and_revoke_keys_in_the_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageApiKeys::class)
            ->callAction('create', ['name' => 'Zoho CRM', 'abilities' => ['leads:read'], 'expires' => '90'])
            ->assertNotified('API key created — copy it now');

        $token = PersonalAccessToken::sole();
        $this->assertSame('Zoho CRM', $token->name);
        $this->assertSame(['leads:read'], $token->abilities);
        $this->assertTrue($token->expires_at->between(now()->addDays(89), now()->addDays(91)));

        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get(ManageApiKeys::getUrl())->assertForbidden();
    }
}
