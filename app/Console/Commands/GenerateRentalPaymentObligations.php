<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\RentalPaymentSchedule;
use Illuminate\Console\Command;

class GenerateRentalPaymentObligations extends Command
{
    protected $signature = 'rentals:generate-payment-obligations';

    protected $description = 'Ensure recurring rent and initial rental obligations exist for confirmed rentals';

    public function handle(RentalPaymentSchedule $schedule): int
    {
        $created = 0;
        Booking::query()
            ->whereIn('status', ['confirmed', 'active'])
            ->whereNotNull('monthly_rent')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($schedule, &$created): void {
                foreach ($bookings as $booking) {
                    $created += $schedule->ensureForBooking($booking);
                }
            });

        $this->info("Created {$created} missing payment obligation(s).");

        return self::SUCCESS;
    }
}
