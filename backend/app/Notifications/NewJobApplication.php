<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The CV itself is never attached: it stays behind the admin login and its access rules. */
class NewJobApplication extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public JobApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing('vacancy');
        $role = $application->vacancy?->title ?? 'General application';

        return (new MailMessage)
            ->subject("New application: {$application->name} — {$role}")
            ->greeting('New job application')
            ->line("**Position:** {$role}")
            ->line("**Name:** {$application->name}")
            ->line('**Experience:** '.($application->experience_years !== null ? "{$application->experience_years} years" : '—'))
            ->line('**City:** '.($application->city ?? '—'))
            ->line('**Notice period:** '.(JobApplication::NOTICE_PERIODS[$application->notice_period] ?? '—'))
            ->action('Review application & CV', url("/admin/job-applications/{$application->id}"))
            ->line('Contact details and the CV are available in the admin panel to authorised team members only.');
    }
}
