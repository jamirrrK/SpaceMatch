<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InquiryActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Inquiry $inquiry,
        private readonly string $activity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'inquiry_id' => $this->inquiry->id,
            'activity' => $this->activity,
            'space_name' => $this->inquiry->space->space_name,
            'url' => route($notifiable->hasRole('owner') ? 'owner.inquiries.show' : 'renter.inquiries.show', $this->inquiry),
        ];
    }
}
