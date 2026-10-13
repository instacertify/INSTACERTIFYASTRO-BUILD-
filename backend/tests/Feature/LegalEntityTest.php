<?php

namespace Tests\Feature;

use App\Filament\Resources\LegalEntities\Pages\CreateLegalEntity;
use App\Jobs\PublishWebsite;
use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class LegalEntityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_indian_company_signs_legal_notices_but_is_not_listed_in_the_footer(): void
    {
        $this->getJson('/api/v1/site')
            ->assertJsonCount(1, 'data.entities')
            ->assertJsonPath('data.entities.0.name', 'Instacertify Labs Private Limited')
            ->assertJsonPath('data.entities.0.registrationLabel', 'CIN')
            ->assertJsonPath('data.entities.0.registrationNumber', 'U74999UP2022PTC170291')
            ->assertJsonPath('data.entities.0.primary', true)
            ->assertJsonPath('data.entities.0.showOnWebsite', false);
    }

    public function test_admin_adds_a_us_company_and_both_are_shown_main_company_first(): void
    {
        config(['instacertify.frontend_rebuild_hook' => 'http://builder:8787', 'instacertify.site_publish_token' => str_repeat('t', 40)]);
        Queue::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/legal-entities')->assertOk()->assertSee('Instacertify Labs Private Limited');

        Livewire::test(CreateLegalEntity::class)
            ->fillForm(['name' => 'Instacertify Inc.', 'market' => 'United States', 'country' => 'US', 'email' => 'not-an-email'])
            ->call('create')
            ->assertHasFormErrors(['email']);

        Livewire::test(CreateLegalEntity::class)
            ->fillForm(['name' => 'Instacertify Inc.', 'market' => 'United States', 'country' => 'US', 'registration_number' => '12-3456789', 'address' => '1 Main St, Wilmington, DE 19801, USA', 'show_on_website' => true])
            ->assertSchemaStateSet(['registration_label' => 'EIN'])
            ->call('create')
            ->assertHasNoFormErrors();
        Queue::assertPushed(PublishWebsite::class);

        LegalEntity::where('country', 'IN')->update(['show_on_website' => true]);

        $this->getJson('/api/v1/site')
            ->assertJsonCount(2, 'data.entities')
            ->assertJsonPath('data.entities.0.market', 'India')
            ->assertJsonPath('data.entities.1.name', 'Instacertify Inc.')
            ->assertJsonPath('data.entities.1.primary', false)
            ->assertJsonPath('data.entities.1.showOnWebsite', true);
    }

    public function test_there_is_only_one_main_company(): void
    {
        $us = LegalEntity::create(['name' => 'Instacertify Inc.', 'market' => 'United States', 'country' => 'US']);

        $us->update(['is_primary' => true]);

        $this->assertSame(['Instacertify Inc.'], LegalEntity::where('is_primary', true)->pluck('name')->all());
    }

    public function test_only_admins_manage_company_entities(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        $this->get('/admin/legal-entities')->assertForbidden();
    }
}
