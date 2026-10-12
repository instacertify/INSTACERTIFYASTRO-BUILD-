<?php

namespace App\Notifications;

use Filament\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** The "Forgot password?" email, sent from the mailbox under Settings → Email & notifications. */
class ResetAdminPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your Instacertify admin password')
            ->greeting('Hello '.($notifiable->name ?? '').',')
            ->line('Someone asked to reset the password for your Instacertify admin account ('.$notifiable->email.').')
            ->action('Choose a new password', $this->url)
            ->line("This link works once and expires in {$minutes} minutes.")
            ->line('If you did not ask for this, ignore this email: your password stays the same.');
    }
}
