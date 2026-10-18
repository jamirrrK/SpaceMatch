<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Support\Facades\DB;

class RentalReminderDispatcher
{
    public function sendOnce(
        User $recipient,
        string $key,
        string $type,
        string $title,
        string $message,
        string $url
    ): bool {
        return DB::transaction(function () use ($recipient, $key, $type, $title, $message, $url): bool {
            $inserted = DB::table('rental_reminders')->insertOrIgnore([
                'reminder_key' => $key,
                'user_id' => $recipient->id,
                'reminder_type' => $type,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted !== 1) {
                return false;
            }

            $recipient->notify(new RentalWorkflowNotification($title, $message, $url));

            return true;
        });
    }
}
