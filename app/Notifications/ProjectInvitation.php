<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectInvitation extends Notification
{
    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $project = $this->invitation->project;
        $inviter = $this->invitation->inviter?->name ?? config('app.name');

        return (new MailMessage)
            ->subject(__(':name invited you to :project', ['name' => $inviter, 'project' => $project->name]))
            ->greeting(__('Hello!'))
            ->line(__(':name has invited you to help manage the costs of :project.', ['name' => $inviter, 'project' => $project->name]))
            ->action(__('Join the project'), $this->invitation->url())
            ->line(__('If you don\'t have an account yet, the button lets you create one.'));
    }
}
