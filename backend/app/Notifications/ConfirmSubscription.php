<?php

namespace App\Notifications;

use App\Models\Subscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ConfirmSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscriber $subscriber) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your Instacertify updates subscription')
            ->greeting('One click to go')
            ->line('Please confirm you want BIS, CE, FCC and other regulatory updates from Instacertify.')
            ->action('Confirm subscription', URL::temporarySignedRoute('api.subscribers.confirm', now()->addDays(7), ['subscriber' => $this->subscriber]))
            ->line('Didn’t sign up? Just ignore this email — you won’t hear from us.');
    }
}
