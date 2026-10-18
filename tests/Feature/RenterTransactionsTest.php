<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\RentalRequest;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RenterTransactionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_can_save_and_remove_a_public_property_without_duplicates(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $property = $this->publishedSpace()->property;

        $this->actingAs($renter)->post(route('renter.saved-properties.store', $property))->assertRedirect();
        $this->post(route('renter.saved-properties.store', $property))->assertRedirect();

        $this->assertDatabaseCount('saved_properties', 1);
        $this->get(route('renter.saved-properties.index'))->assertOk()->assertSee($property->property_name);

        $this->delete(route('renter.saved-properties.destroy', $property))->assertRedirect();
        $this->assertDatabaseCount('saved_properties', 0);
    }

    public function test_renter_request_can_be_created_and_owner_approval_creates_a_confirmed_booking(): void
    {
        $space = $this->publishedSpace(['deposit_amount' => 5000, 'advance_months' => 2]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $owner = $space->property->owner;

        $this->actingAs($renter)->post(route('renter.requests.store', $space), [
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 2,
            'duration_months' => 6,
            'contact_phone' => '+639171234567',
            'message' => 'I am interested in this space.',
        ])->assertRedirect(route('renter.requests.index'));

        $rentalRequest = RentalRequest::query()->firstOrFail();
        $this->assertSame(RentalRequest::STATUS_PENDING, $rentalRequest->status);
        $this->assertDatabaseHas('rental_request_histories', [
            'rental_request_id' => $rentalRequest->id,
            'event' => 'submitted',
            'to_status' => RentalRequest::STATUS_PENDING,
        ]);

        $this->actingAs($owner)->patch(route('owner.requests.update', $rentalRequest), [
            'status' => RentalRequest::STATUS_APPROVED,
        ])->assertRedirect();

        $rentalRequest->refresh();
        $booking = $rentalRequest->booking;
        $this->assertSame(RentalRequest::STATUS_APPROVED, $rentalRequest->status);
        $this->assertDatabaseHas('rental_request_histories', [
            'rental_request_id' => $rentalRequest->id,
            'event' => 'owner_decision',
            'from_status' => RentalRequest::STATUS_PENDING,
            'to_status' => RentalRequest::STATUS_APPROVED,
        ]);
        $this->assertNotNull($booking);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(2, $booking->occupants);
        $this->assertSame((float) $space->price, (float) $booking->monthly_rent);
        $this->assertSame(5000.0, (float) $booking->deposit_amount);
        $this->assertSame(2, $booking->advance_months);
        $this->assertNotEmpty($booking->reference);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'payment_type' => 'monthly_rent',
            'payment_status' => 'pending',
        ]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'payment_type' => 'security_deposit',
            'amount' => 5000,
        ]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'payment_type' => 'advance_rent',
            'amount' => (float) $space->price * 2,
        ]);
        $this->assertSame(4, Payment::query()->where('booking_id', $booking->id)->where('payment_type', 'monthly_rent')->count());
        $this->artisan('rentals:generate-payment-obligations')->assertSuccessful();
        $this->assertSame(4, Payment::query()->where('booking_id', $booking->id)->where('payment_type', 'monthly_rent')->count());
        $this->assertDatabaseCount('notifications', 2);
        $this->actingAs($renter)->get(route('notifications.index'))->assertOk()
            ->assertSee('Rental request approved');
        $notificationId = $renter->unreadNotifications()->firstOrFail()->id;
        $this->patch(route('notifications.read', $notificationId))->assertRedirect();
        $this->assertDatabaseHas('notifications', ['id' => $notificationId]);
    }

    public function test_duplicate_requests_and_capacity_overbooking_are_rejected(): void
    {
        $space = $this->publishedSpace(['capacity' => 1]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $owner = $space->property->owner;
        $payload = [
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234567',
        ];

        $this->actingAs($renter)->post(route('renter.requests.store', $space), $payload)->assertRedirect(route('renter.requests.index'));
        $rentalRequest = RentalRequest::query()->firstOrFail();

        $this->post(route('renter.requests.store', $space), $payload)->assertSessionHasErrors('space');

        Booking::query()->create([
            'renter_id' => User::factory()->create(['role' => User::ROLE_RENTER])->id,
            'space_id' => $space->id,
            'start_date' => $payload['move_in_date'],
            'end_date' => now()->addMonths(4)->toDateString(),
            'status' => 'confirmed',
            'occupants' => 1,
        ]);

        $this->actingAs($owner)->patch(route('owner.requests.update', $rentalRequest), [
            'status' => RentalRequest::STATUS_APPROVED,
        ])->assertSessionHasErrors('status');
        $this->assertNull($rentalRequest->fresh()->booking_id);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertSame(RentalRequest::STATUS_PENDING, $rentalRequest->fresh()->status);
    }

    public function test_other_renters_and_owners_cannot_read_or_change_another_users_requests(): void
    {
        $space = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $rentalRequest = RentalRequest::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234567',
        ]);

        $this->actingAs($otherRenter)->patch(route('renter.requests.cancel', $rentalRequest))->assertNotFound();
        $this->actingAs($otherOwner)->patch(route('owner.requests.update', $rentalRequest), [
            'status' => RentalRequest::STATUS_REJECTED,
            'response_message' => 'Not available.',
        ])->assertNotFound();
        $this->actingAs($otherRenter)->get(route('owner.requests.index'))->assertForbidden();
    }

    public function test_owner_rejection_does_not_create_a_booking_and_notifies_the_renter(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $rentalRequest = RentalRequest::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234567',
        ]);

        $this->actingAs($owner)->patch(route('owner.requests.update', $rentalRequest), [
            'status' => RentalRequest::STATUS_REJECTED,
            'response_message' => 'The dates are unavailable.',
        ])->assertRedirect();

        $this->assertSame(RentalRequest::STATUS_REJECTED, $rentalRequest->fresh()->status);
        $this->assertNull($rentalRequest->fresh()->booking_id);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $renter->id,
        ]);
    }

    public function test_a_renter_cannot_request_their_own_property(): void
    {
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $property = Property::factory()->published()->create(['owner_id' => $renter->id]);
        $space = Space::factory()->published()->create(['property_id' => $property->id]);

        $this->actingAs($renter)->post(route('renter.requests.store', $space), [
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234567',
        ])->assertSessionHasErrors('space');
    }

    public function test_owner_can_confirm_a_manual_payment_but_cannot_change_another_owners_payment(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-TEST-PAYMENT',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'confirmed',
            'monthly_rent' => $space->price,
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'monthly_rent',
            'amount' => $space->price,
            'due_date' => now()->toDateString(),
            'payment_status' => 'pending',
        ]);
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($otherOwner)->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'gcash',
        ])->assertNotFound();

        $this->actingAs($owner)->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'gcash',
            'reference' => 'MANUAL-RECEIPT-1',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payment_status' => 'paid',
            'confirmed_by' => $owner->id,
            'reference' => 'MANUAL-RECEIPT-1',
            'paid_amount' => $space->price,
        ]);
        $transaction = PaymentTransaction::query()->firstOrFail();
        $this->assertSame((float) $space->price, (float) $transaction->amount);
        $this->actingAs($renter)->get(route('payments.receipts.show', $transaction))
            ->assertOk()->assertHeader('content-disposition', 'attachment; filename="'.$transaction->receipt_number.'.html"')
            ->assertSee($transaction->receipt_number);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $renter->id,
        ]);
    }

    public function test_owner_booking_lifecycle_is_date_guarded_and_audited_and_completed_renter_can_review_once(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $start = now()->addDays(2)->toDateString();
        $end = now()->addDays(5)->toDateString();
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-TEST-LIFECYCLE',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'confirmed',
            'monthly_rent' => $space->price,
        ]);
        BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'actor_id' => $owner->id,
            'from_status' => null,
            'to_status' => 'confirmed',
        ]);

        $this->actingAs($owner)->patch(route('owner.bookings.update', $booking), ['status' => 'active'])
            ->assertSessionHasErrors('status');

        $this->travelTo(Carbon::parse($start));
        $this->patch(route('owner.bookings.update', $booking), ['status' => 'active'])->assertRedirect();
        $this->travelTo(Carbon::parse($end));
        $this->patch(route('owner.bookings.update', $booking), ['status' => 'completed'])->assertRedirect();
        $this->assertSame(['confirmed', 'active', 'completed'], BookingStatusHistory::query()
            ->where('booking_id', $booking->id)->orderBy('id')->pluck('to_status')->all());

        $this->actingAs($renter)->post(route('reviews.store', $booking), [
            'rating' => 5,
            'comment' => 'The stay matched the listing.',
        ])->assertRedirect(route('reviews.index'));
        $this->assertDatabaseHas('reviews', ['booking_id' => $booking->id, 'rating' => 5]);
        $this->post(route('reviews.store', $booking), ['rating' => 4])->assertSessionHasErrors('booking');
        $this->get(route('properties.show', $space->property))->assertOk()
            ->assertSee('The stay matched the listing.')
            ->assertSee('5.0/5');
    }

    public function test_owner_can_record_partial_payments_and_receipts_exist_only_for_confirmed_amounts(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-PARTIAL-PAYMENT',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'confirmed',
            'monthly_rent' => 1000,
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'monthly_rent',
            'amount' => 1000,
            'due_date' => now()->toDateString(),
            'payment_status' => 'pending',
        ]);

        $this->actingAs($owner)->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'amount' => '400.25',
        ])->assertRedirect();
        $payment->refresh();
        $this->assertSame('partially_paid', $payment->payment_status);
        $this->assertSame(599.75, $payment->outstanding_balance);
        $this->assertSame(1, PaymentTransaction::query()->where('payment_id', $payment->id)->count());

        $this->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'amount' => '600',
        ])->assertSessionHasErrors('amount');
        $this->assertSame(1, PaymentTransaction::query()->where('payment_id', $payment->id)->count());

        $this->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'amount' => '599.75',
        ])->assertRedirect();
        $this->assertSame('paid', $payment->fresh()->payment_status);
        $this->assertSame(2, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(2, PaymentTransaction::query()->where('payment_id', $payment->id)
            ->whereNotNull('receipt_number')->count());

        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($otherRenter)->get(route('payments.receipts.show', PaymentTransaction::query()->firstOrFail()))
            ->assertNotFound();
    }

    public function test_booking_cancellation_and_termination_are_audited_and_preserve_confirmed_payment_records(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-CANCEL-BOOKING',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'status' => 'confirmed',
            'monthly_rent' => 1000,
        ]);
        $paid = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'advance_rent',
            'amount' => 1000,
            'paid_amount' => 1000,
            'due_date' => now()->addDays(10)->toDateString(),
            'payment_status' => 'paid',
        ]);
        $pending = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'monthly_rent',
            'amount' => 1000,
            'due_date' => now()->addMonths(2)->toDateString(),
            'payment_status' => 'pending',
        ]);

        $this->actingAs($owner)->patch(route('owner.bookings.cancel', $booking), ['reason' => 'Owner schedule changed'])
            ->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('cancelled', $pending->fresh()->payment_status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => 'confirmed',
            'to_status' => 'cancelled',
            'actor_id' => $owner->id,
        ]);

        $replacementRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($replacementRenter)->post(route('renter.requests.store', $space), [
            'move_in_date' => $booking->start_date->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234568',
        ])->assertRedirect(route('renter.requests.index'));
        $replacementRequest = RentalRequest::query()->where('renter_id', $replacementRenter->id)->firstOrFail();
        $this->actingAs($owner)->patch(route('owner.requests.update', $replacementRequest), [
            'status' => RentalRequest::STATUS_APPROVED,
        ])->assertRedirect();
        $this->assertSame('confirmed', $replacementRequest->fresh()->booking->status);

        $activeBooking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-TERMINATE-BOOKING',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'active',
            'monthly_rent' => 1000,
        ]);
        $futureDue = Payment::query()->create([
            'booking_id' => $activeBooking->id,
            'payment_type' => 'monthly_rent',
            'amount' => 1000,
            'due_date' => now()->addMonth()->toDateString(),
            'payment_status' => 'pending',
        ]);
        $this->actingAs($renter)->patch(route('renter.bookings.terminate', $activeBooking), [
            'reason' => 'Renter needs to relocate',
        ])->assertRedirect();
        $this->assertSame('terminated', $activeBooking->fresh()->status);
        $this->assertNotNull($activeBooking->fresh()->ended_early_at);
        $this->assertSame($renter->id, $activeBooking->fresh()->terminated_by);
        $this->assertSame('cancelled', $futureDue->fresh()->payment_status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $activeBooking->id,
            'from_status' => 'active',
            'to_status' => 'terminated',
        ]);
    }

    public function test_booking_cancellation_rejects_unauthorized_users_and_active_bookings(): void
    {
        $space = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_RENTER]))
            ->patch(route('renter.bookings.terminate', $booking), ['reason' => 'Not my booking'])->assertNotFound();
        $this->actingAs($renter)->patch(route('renter.bookings.cancel', $booking), ['reason' => 'No longer needed'])
            ->assertSessionHasErrors('booking');
        $this->assertSame('active', $booking->fresh()->status);
    }

    public function test_renter_can_cancel_a_confirmed_booking_before_move_in_and_future_obligations(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-RENTER-CANCEL',
            'start_date' => now()->addDays(8)->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'confirmed',
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'security_deposit',
            'amount' => 5000,
            'due_date' => $booking->start_date,
            'payment_status' => 'pending',
        ]);

        $this->actingAs($renter)->patch(route('renter.bookings.cancel', $booking), [
            'reason' => 'Renter plans changed',
        ])->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('cancelled', $payment->fresh()->payment_status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'actor_id' => $renter->id,
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
        ]);
    }

    public function test_rental_reminder_command_is_idempotent_for_move_in_upcoming_and_overdue_items(): void
    {
        $space = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-REMINDER-BOOKING',
            'start_date' => today()->addDay(),
            'end_date' => today()->addMonths(3),
            'status' => 'confirmed',
            'monthly_rent' => 1000,
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'monthly_rent',
            'amount' => 1000,
            'due_date' => today()->addDays(7),
            'payment_status' => 'pending',
        ]);
        Payment::query()->create([
            'booking_id' => $booking->id,
            'payment_type' => 'advance_rent',
            'amount' => 1000,
            'due_date' => today()->subDay(),
            'payment_status' => 'partially_paid',
            'paid_amount' => 250,
        ]);

        $this->artisan('rentals:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('rental_reminders', 3);
        $this->assertDatabaseCount('notifications', 3);
        $this->artisan('rentals:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('rental_reminders', 3);
        $this->assertDatabaseCount('notifications', 3);
    }

    public function test_owner_can_request_more_information_and_renter_can_reply_before_a_final_decision(): void
    {
        $space = $this->publishedSpace();
        $owner = $space->property->owner;
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $rentalRequest = RentalRequest::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'move_in_date' => now()->addMonth()->toDateString(),
            'occupants' => 1,
            'duration_months' => 3,
            'contact_phone' => '09171234567',
        ]);

        $this->actingAs($owner)->patch(route('owner.requests.update', $rentalRequest), [
            'status' => RentalRequest::STATUS_MORE_INFORMATION,
            'response_message' => 'Please confirm the number of occupants.',
        ])->assertRedirect();
        $this->actingAs($renter)->patch(route('renter.requests.respond', $rentalRequest), [
            'renter_response' => 'There will be one occupant.',
        ])->assertRedirect();

        $this->assertSame(RentalRequest::STATUS_PENDING, $rentalRequest->fresh()->status);
        $this->assertSame('There will be one occupant.', $rentalRequest->fresh()->renter_response);
        $this->assertDatabaseHas('rental_request_histories', [
            'rental_request_id' => $rentalRequest->id,
            'event' => 'owner_decision',
            'from_status' => RentalRequest::STATUS_PENDING,
            'to_status' => RentalRequest::STATUS_MORE_INFORMATION,
        ]);
        $this->assertDatabaseHas('rental_request_histories', [
            'rental_request_id' => $rentalRequest->id,
            'event' => 'renter_reply',
            'from_status' => RentalRequest::STATUS_MORE_INFORMATION,
            'to_status' => RentalRequest::STATUS_PENDING,
            'notes' => 'There will be one occupant.',
        ]);
        $this->assertDatabaseCount('notifications', 2);
    }

    private function publishedSpace(array $attributes = []): Space
    {
        $property = Property::factory()->published()->create();

        return Space::factory()->published()->create($attributes + ['property_id' => $property->id]);
    }
}
