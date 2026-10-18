<?php

namespace App\Notifications;

use App\Models\ViewingAppointment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViewingActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ViewingAppointment $appointment,
        private readonly string $activity,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'viewing_appointment_id' => $this->appointment->id,
            'activity' => $this->activity,
            'space_name' => $this->appointment->space->space_name,
            'url' => route($notifiable->hasRole('owner') ? 'owner.viewings.show' : 'renter.viewings.show', $this->appointment),
        ];
    }
}
