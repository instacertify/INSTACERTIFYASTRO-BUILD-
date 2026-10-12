<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use App\Notifications\ResetAdminPassword;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_the_login_page_offers_forgot_password(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('Forgot password?')->assertSee('/admin/password-reset/request');
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_the_reset_link_goes_to_the_account_email_only(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'nikhil@instacertify.com', 'role' => 'admin']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nikhil@instacertify.com'])
            ->call('request')
            ->assertNotified();

        Notification::assertSentTo($user, ResetAdminPassword::class, function (ResetAdminPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Reset your Instacertify admin password'
                && str_contains($mail->actionUrl, '/admin/password-reset/reset')
                && $notification instanceof ShouldQueue;
        });
        Notification::assertCount(1);
    }

    public function test_an_unknown_email_gets_the_same_answer_and_nothing_is_sent(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'nikhil@instacertify.com', 'role' => 'admin']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'stranger@example.com'])
            ->call('request')
            ->assertNotified(__(Password::RESET_LINK_SENT));

        Notification::assertNothingSent();
    }

    public function test_a_new_password_needs_twelve_characters(): void
    {
        $user = User::factory()->create(['email' => 'nikhil@instacertify.com', 'role' => 'admin', 'password' => Hash::make('old-Password-123')]);
        $token = Password::broker('users')->createToken($user);

        Livewire::withQueryParams(['email' => $user->email, 'token' => $token])
            ->test(ResetPassword::class)
            ->fillForm(['password' => 'short-1', 'passwordConfirmation' => 'short-1'])
            ->call('resetPassword')
            ->assertHasFormErrors(['password']);
        $this->assertTrue(Hash::check('old-Password-123', $user->fresh()->password));

        Livewire::withQueryParams(['email' => $user->email, 'token' => $token])
            ->test(ResetPassword::class)
            ->fillForm(['password' => 'new-Password-456', 'passwordConfirmation' => 'new-Password-456'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();
        $this->assertTrue(Hash::check('new-Password-456', $user->fresh()->password));
    }
}
