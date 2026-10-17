<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;

class RentalPaymentSchedule
{
    public function ensureForBooking(Booking $booking): int
    {
        if (! in_array($booking->status, ['confirmed', 'active'], true) || ! $booking->monthly_rent) {
            return 0;
        }

        $advanceMonths = (int) ($booking->advance_months ?? 0);
        $rows = [];
        if ((float) $booking->deposit_amount > 0) {
            $rows[] = $this->obligation($booking, 'security_deposit', $booking->deposit_amount, $booking->start_date);
        }
        if ($advanceMonths > 0) {
            $rows[] = $this->obligation(
                $booking,
                'advance_rent',
                (float) $booking->monthly_rent * $advanceMonths,
                $booking->start_date
            );
        }

        for ($monthOffset = $advanceMonths; ; $monthOffset++) {
            $dueDate = $booking->start_date->copy()->addMonthsNoOverflow($monthOffset);
            if ($dueDate->gt($booking->end_date)) {
                break;
            }
            $rows[] = $this->obligation($booking, 'monthly_rent', $booking->monthly_rent, $dueDate);
        }

        $inserted = 0;
        foreach ($rows as $row) {
            $inserted += DB::table('payments')->insertOrIgnore($row);
        }

        return $inserted;
    }

    private function obligation(Booking $booking, string $type, float|string $amount, $dueDate): array
    {
        $now = now();

        return [
            'booking_id' => $booking->id,
            'payment_type' => $type,
            'amount' => $amount,
            'paid_amount' => 0,
            'due_date' => $dueDate->toDateString(),
            'payment_status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
