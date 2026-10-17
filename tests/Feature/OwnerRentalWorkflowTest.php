<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerRentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_submits_request_and_owner_approval_confirms_without_marking_payment_paid(): void
    {
        [$owner, $renter, $space] = $this->listing();
        $start = now()->addDays(5)->toDateString();
        $end = now()->addDays(36)->toDateString();

        $this->actingAs($renter)->post(route('bookings.store'), [
            'space_id' => $space->id, 'start_date' => $start, 'end_date' => $end,
            'occupants' => 2, 'renter_message' => 'I am interested in this space.',
        ])->assertRedirect(route('bookings.index'))->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('pending', $booking->status);
        $this->assertSame('2.00', (string) $booking->agreed_amount);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $owner->id]);
        $this->actingAs($owner)->post(route('owner.bookings.decision', $booking), ['decision' => 'approve'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame('pending', $booking->fresh()->payment->payment_status);
        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'amount' => '2.00', 'payment_status' => 'pending']);
    }

    public function test_owner_can_request_more_information_and_renter_response_reopens_request(): void
    {
        [$owner, $renter, $space] = $this->listing();
        $booking = $this->booking($renter, $space);

        $this->actingAs($owner)->post(route('owner.bookings.decision', $booking), [
            'decision' => 'more_details', 'reason' => 'How many people will live in the unit?',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('awaiting_renter_information', $booking->fresh()->status);
        $question = $booking->informationRequests()->sole();

        $this->actingAs($renter)->post(route('booking-information.respond', $question), ['response' => 'Two adults.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame('Two adults.', $question->fresh()->response);
        $this->actingAs($owner)->get(route('owner.rentals.index'))->assertOk()->assertSee('Two adults.');
    }

    public function test_rejection_overlap_prevention_and_owner_data_scope(): void
    {
        [$owner, $renter, $space] = $this->listing();
        $overlap = $this->booking($renter, $space, ['status' => 'confirmed']);
        $request = $this->booking($renter, $space, ['start_date' => $overlap->start_date->addDays(2), 'end_date' => $overlap->end_date->addDays(2)]);
        $this->actingAs($owner)->post(route('owner.bookings.decision', $request), ['decision' => 'approve'])
            ->assertSessionHasErrors('decision');
        $this->assertSame('pending', $request->fresh()->status);

        $this->post(route('owner.bookings.decision', $request), ['decision' => 'reject', 'reason' => 'Dates unavailable.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $secondOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($secondOwner)->post(route('owner.bookings.decision', $request), ['decision' => 'approve'])->assertNotFound();
        $this->actingAs($secondOwner)->get(route('owner.reports'))->assertOk()->assertSee('0.00');
    }

    public function test_owner_can_resolve_maintenance_record_payments_and_view_paid_only_reports(): void
    {
        [$owner, $renter, $space] = $this->listing();
        $booking = $this->booking($renter, $space, ['status' => 'confirmed']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => '1800.00', 'payment_status' => 'pending']);
        $maintenance = MaintenanceRequest::create(['booking_id' => $booking->id, 'description' => 'The kitchen tap is leaking.', 'status' => 'open']);

        $this->actingAs($renter)->post(route('maintenance.store'), ['booking_id' => $booking->id, 'description' => 'The bedroom light stopped working.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('owner.maintenance.update', $maintenance), [
            'status' => 'resolved', 'owner_response' => 'Replaced the tap washer.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotNull($maintenance->fresh()->resolved_at);
        $this->actingAs($owner)->patch(route('owner.payments.update', $payment), [
            'payment_status' => 'paid', 'payment_method' => 'bank transfer', 'payment_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($owner)->get(route('owner.reports'))->assertOk()->assertSee('1,800.00');
        $this->assertSame('paid', $payment->fresh()->payment_status);
    }

    public function test_owner_can_activate_current_rental_and_complete_only_after_the_end_date(): void
    {
        [$owner, $renter, $space] = $this->listing();
        $booking = $this->booking($renter, $space, [
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        $this->actingAs($owner)->patch(route('owner.bookings.activate', $booking))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $booking->fresh()->status);
        $this->patch(route('owner.bookings.complete', $booking))->assertSessionHasErrors('rental');

        $booking->update(['end_date' => now()->subDay()->toDateString()]);
        $this->patch(route('owner.bookings.complete', $booking))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->completed_at);
    }

    private function listing(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $property = Property::factory()->published()->create(['owner_id' => $owner->id]);
        $space = Space::factory()->published()->create(['property_id' => $property->id, 'price' => '1.00', 'capacity' => 3]);

        return [$owner, $renter, $space];
    }

    private function booking(User $renter, Space $space, array $overrides = []): Booking
    {
        return Booking::create(array_replace([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'occupants' => 1,
            'agreed_amount' => '1.00',
            'status' => 'pending',
        ], $overrides));
    }
}
