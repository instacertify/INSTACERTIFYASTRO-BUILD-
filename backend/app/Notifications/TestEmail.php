<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestEmail extends Notification
{
    public function __construct(public string $sentBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email from the Instacertify website')
            ->greeting('Email is working')
            ->line('New website enquiries will arrive at this address the moment they are submitted.')
            ->line("Sent by {$this->sentBy} from Settings → Email & notifications at ".now()->timezone('Asia/Kolkata')->format('j M Y, g:i a').' IST.')
            ->action('Open leads', url('/admin/leads'));
    }
}
