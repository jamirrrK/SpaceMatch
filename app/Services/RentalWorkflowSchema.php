<?php

namespace App\Services;

use Illuminate\Support\Facades\Schema;

class RentalWorkflowSchema
{
    public function ensureAvailable(): void
    {
        $bookingColumns = [
            'renter_message',
            'agreed_amount',
            'decision_reason',
            'decided_at',
            'confirmed_at',
            'completed_at',
        ];

        $ready = Schema::hasTable('booking_information_requests')
            && collect($bookingColumns)->every(fn (string $column): bool => Schema::hasColumn('bookings', $column));

        abort_unless($ready, response()->view('errors.rental-workflow-schema', [], 503));
    }
}
