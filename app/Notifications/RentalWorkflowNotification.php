<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RentalWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $message, private readonly string $url) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url];
    }
}
