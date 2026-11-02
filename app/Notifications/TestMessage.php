<?php

namespace App\Notifications;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent from Project settings to check that a recipient gets messages. */
class TestMessage extends Notification
{
    public function __construct(public string $project) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? array_keys($notifiable->routes) : ['mail'];
    }

    public function text(): string
    {
        return __('Test message from :project. You will get a message here when a cost is added, changed or deleted.', ['project' => $this->project]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->project.': '.__('Test message'))->line($this->text());
    }

    public function toWhatsApp(object $notifiable): string
    {
        return '🏠 '.$this->text();
    }

    public function toSms(object $notifiable): string
    {
        return $this->text();
    }
}
