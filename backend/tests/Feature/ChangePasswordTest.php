<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_every_role_can_open_the_change_password_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']))
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('New password');
    }

    public function test_password_changes_only_with_the_current_password(): void
    {
        $user = User::factory()->create(['role' => 'editor', 'password' => Hash::make('old-Password-123')]);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'new-Password-456', 'passwordConfirmation' => 'new-Password-456', 'currentPassword' => 'wrong-password'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);
        $this->assertTrue(Hash::check('old-Password-123', $user->fresh()->password));

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'new-Password-456', 'passwordConfirmation' => 'new-Password-456', 'currentPassword' => 'old-Password-123'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('new-Password-456', $user->fresh()->password));
    }

    public function test_site_settings_links_to_the_change_password_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/manage-site')
            ->assertOk()
            ->assertSee('Change password')
            ->assertSee('/admin/profile');
    }
}
