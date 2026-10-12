<?php

namespace Tests\Feature;

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_api_lists_published_offices_in_order(): void
    {
        Office::create(['name' => 'Gujarat', 'street' => 'FF/17, Umannagar Shopping Center', 'city' => 'Vadodara', 'region' => 'Gujarat', 'postal_code' => '390025', 'sort' => 2, 'hours' => 'Mon–Sat']);
        Office::create(['name' => 'Delhi NCR', 'type' => 'head', 'street' => 'A-34, Sector 63 A', 'city' => 'Noida', 'region' => 'Uttar Pradesh', 'postal_code' => '201301', 'sort' => 1,
            'map' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!abc" width="600" height="450"></iframe>']);
        Office::create(['name' => 'Hidden', 'street' => 'Somewhere', 'is_published' => false]);

        $this->getJson('/api/v1/site')
            ->assertJsonCount(2, 'data.offices')
            ->assertJsonPath('data.offices.0.name', 'Delhi NCR')
            ->assertJsonPath('data.offices.0.typeLabel', 'Head office')
            ->assertJsonPath('data.offices.0.address', 'A-34, Sector 63 A, Noida, Uttar Pradesh 201301')
            ->assertJsonPath('data.offices.0.mapEmbed', 'https://www.google.com/maps/embed?pb=!1m18!abc')
            ->assertJsonPath('data.offices.1.hours', 'Mon–Sat')
            ->assertJsonPath('data.offices.1.mapEmbed', 'https://www.google.com/maps?q='.rawurlencode('FF/17, Umannagar Shopping Center, Vadodara, Gujarat 390025').'&output=embed');
    }

    public function test_full_address_does_not_repeat_parts_already_in_the_street(): void
    {
        $office = new Office(['street' => "1st Floor, Abha Building,\nKalyan (West)", 'city' => 'Kalyan', 'region' => 'Maharashtra', 'postal_code' => '421301']);

        $this->assertSame('1st Floor, Abha Building, Kalyan (West), Maharashtra 421301', $office->fullAddress());
    }

    public function test_editors_add_offices_with_validated_map_and_pin_code(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/admin/offices')->assertOk();

        Livewire::test(CreateOffice::class)
            ->fillForm(['name' => 'Bengaluru', 'street' => '12, MG Road', 'city' => 'Bengaluru', 'region' => 'Karnataka', 'postal_code' => '5600', 'map' => 'https://example.com/map'])
            ->call('create')
            ->assertHasFormErrors(['postal_code', 'map']);

        Livewire::test(CreateOffice::class)
            ->fillForm([
                'name' => 'Bengaluru', 'type' => 'lab', 'street' => '12, MG Road', 'city' => 'Bengaluru', 'region' => 'Karnataka', 'postal_code' => '560001',
                'email' => 'blr@instacertify.com', 'contact_person' => 'Anita Rao', 'hours' => 'Mon–Fri, 10–6',
                'map' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!xyz"></iframe>',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $office = Office::sole();
        $this->assertSame('lab', $office->type);
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18!xyz', $office->mapEmbed());

        Livewire::test(EditOffice::class, ['record' => $office->getRouteKey()])
            ->assertSee('Showing your embedded map')
            ->fillForm(['map' => null])
            ->assertSee('Pin placed from the address');
    }

    public function test_there_is_only_one_head_office(): void
    {
        $old = Office::create(['name' => 'Delhi NCR', 'type' => 'head', 'street' => 'A-34, Sector 63 A']);
        $new = Office::create(['name' => 'Mumbai', 'type' => 'branch', 'street' => 'BKC']);

        $new->update(['type' => 'head']);

        $this->assertSame('branch', $old->fresh()->type);
        $this->assertSame('head', $new->fresh()->type);
    }

    public function test_sales_cannot_manage_offices(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']));

        $this->get('/admin/offices')->assertForbidden();
    }
}
