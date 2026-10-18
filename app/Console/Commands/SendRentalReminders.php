<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\RentalReminderDispatcher;
use Illuminate\Console\Command;

class SendRentalReminders extends Command
{
    protected $signature = 'rentals:send-reminders';

    protected $description = 'Send idempotent database reminders for move-in and rent due dates';

    public function handle(RentalReminderDispatcher $dispatcher): int
    {
        $tomorrow = today()->addDay();
        $bookings = Booking::query()
            ->with('renter')
            ->where('status', 'confirmed')
            ->whereDate('start_date', $tomorrow)
            ->get();
        foreach ($bookings as $booking) {
            $dispatcher->sendOnce(
                $booking->renter,
                "booking:{$booking->id}:move-in:{$booking->start_date->toDateString()}",
                'move_in',
                'Move-in reminder',
                'Your move-in date for booking '.$booking->reference.' is tomorrow.',
                route('bookings.index')
            );
        }

        $upcomingPayments = Payment::query()
            ->with('booking.renter')
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->whereDate('due_date', $tomorrow->copy()->addDays(6))
            ->get();
        foreach ($upcomingPayments as $payment) {
            $dispatcher->sendOnce(
                $payment->booking->renter,
                "payment:{$payment->id}:due:{$payment->due_date->toDateString()}",
                'rent_upcoming',
                'Upcoming rent payment',
                'A '.$payment->payment_type.' obligation for booking '.$payment->booking->reference.' is due in seven days.',
                route('payments.index')
            );
        }

        $overduePayments = Payment::query()
            ->with('booking.renter')
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->whereDate('due_date', '<', today())
            ->get();
        foreach ($overduePayments as $payment) {
            $daysOverdue = $payment->due_date->diffInDays(today());
            $week = (int) floor(($daysOverdue - 1) / 7);
            $dispatcher->sendOnce(
                $payment->booking->renter,
                "payment:{$payment->id}:overdue-week:{$week}",
                'rent_overdue',
                'Overdue rent balance',
                'A balance remains for the '.$payment->payment_type.' obligation on booking '.$payment->booking->reference.'.',
                route('payments.index')
            );
        }

        $this->info('Rental reminders processed.');

        return self::SUCCESS;
    }
}
