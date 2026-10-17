<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_dashboard_reports_real_collections_balances_and_upcoming_rentals_only(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $booking = $this->bookingFor($owner, 'active');
        $paid = $this->payment($booking, [
            'amount' => 5000,
            'paid_amount' => 5000,
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);
        $partial = $this->payment($booking, [
            'amount' => 12000,
            'paid_amount' => 2000,
            'payment_type' => 'monthly_rent',
            'payment_status' => 'partially_paid',
            'due_date' => today()->subDay(),
            'payment_date' => now(),
        ]);
        $this->payment($booking, [
            'amount' => 4000,
            'payment_status' => 'cancelled',
            'due_date' => today()->addDays(6),
        ]);
        $this->transaction($owner, $paid, 5000);
        $this->transaction($owner, $partial, 2000);

        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $otherBooking = $this->bookingFor($otherOwner, 'active');
        $otherPayment = $this->payment($otherBooking, [
            'amount' => 90000,
            'paid_amount' => 90000,
            'payment_status' => 'paid',
            'payment_date' => now(),
        ]);
        $this->transaction($otherOwner, $otherPayment, 90000);

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk()
            ->assertViewHas('stats', function (array $stats): bool {
                return (float) $stats['collected_total'] === 7000.0
                    && (float) $stats['collected_this_month'] === 7000.0
                    && (float) $stats['outstanding_balance'] === 10000.0
                    && (float) $stats['overdue_balance'] === 10000.0
                    && $stats['active_tenants'] === 1;
            })
            ->assertSee('₱7,000.00')
            ->assertSee('Recent confirmed payments');
    }

    public function test_tenant_directory_is_owner_scoped_searchable_and_excludes_terminal_bookings(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $active = $this->bookingFor($owner, 'active', 'SM-OWNER-ACTIVE');
        $this->bookingFor($owner, 'completed', 'SM-OWNER-COMPLETED');
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $other = $this->bookingFor($otherOwner, 'active', 'SM-OTHER-ACTIVE');

        $this->actingAs($owner)->get(route('owner.tenants.index'))
            ->assertOk()
            ->assertSee($active->reference)
            ->assertDontSee('SM-OWNER-COMPLETED')
            ->assertDontSee($other->reference);

        $this->get(route('owner.tenants.index', ['q' => $other->reference]))
            ->assertOk()
            ->assertSee('No matching tenants');
    }

    public function test_owner_report_uses_confirmed_transactions_and_prevents_cross_owner_property_filters(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $booking = $this->bookingFor($owner);
        $payment = $this->payment($booking, [
            'amount' => 10000,
            'paid_amount' => 2500,
            'payment_status' => 'partially_paid',
            'due_date' => today()->subDay(),
        ]);
        $this->transaction($owner, $payment, 2500);

        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $otherBooking = $this->bookingFor($otherOwner);
        $otherPayment = $this->payment($otherBooking, ['amount' => 20000]);
        $this->transaction($otherOwner, $otherPayment, 6000);

        $this->actingAs($owner)->get(route('owner.reports.index'))
            ->assertOk()
            ->assertSee('₱2,500.00')
            ->assertSee('₱7,500.00')
            ->assertDontSee($otherBooking->reference);

        $this->get(route('owner.reports.index', ['property_id' => $otherBooking->space->property_id]))
            ->assertSessionHasErrors('property_id');

        $export = $this->get(route('owner.reports.export', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]));
        $export->assertDownload('spacematch-owner-collections.csv');
        $this->assertStringContainsString($payment->booking->reference, $export->streamedContent());
    }

    public function test_owner_can_progress_maintenance_with_required_resolution_note_and_history(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $booking = $this->bookingFor($owner, 'active');
        $maintenance = MaintenanceRequest::query()->create([
            'booking_id' => $booking->id,
            'description' => 'The kitchen tap is leaking.',
            'status' => 'open',
        ]);

        $this->actingAs($owner)->get(route('maintenance.index'))
            ->assertOk()->assertSee('The kitchen tap is leaking.');
        $this->patch(route('owner.maintenance.update', $maintenance), ['status' => 'in_progress'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('owner.maintenance.update', $maintenance), ['status' => 'resolved'])
            ->assertSessionHasErrors('notes');
        $this->patch(route('owner.maintenance.update', $maintenance), [
            'status' => 'resolved',
            'notes' => 'Replaced the worn tap cartridge.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('resolved', $maintenance->fresh()->status);
        $this->assertDatabaseHas('maintenance_request_status_history', [
            'maintenance_request_id' => $maintenance->id,
            'from_status' => 'open',
            'to_status' => 'in_progress',
        ]);
        $this->assertDatabaseHas('maintenance_request_status_history', [
            'maintenance_request_id' => $maintenance->id,
            'from_status' => 'in_progress',
            'to_status' => 'resolved',
            'notes' => 'Replaced the worn tap cartridge.',
        ]);

        $this->actingAs($booking->renter)->patch(route('owner.maintenance.update', $maintenance), [
            'status' => 'closed',
        ])->assertForbidden();
    }

    public function test_owner_cannot_read_or_update_another_owners_maintenance_request(): void
    {
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $booking = $this->bookingFor($otherOwner, 'active');
        $maintenance = MaintenanceRequest::query()->create([
            'booking_id' => $booking->id,
            'description' => 'A private maintenance report.',
            'status' => 'open',
        ]);

        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($owner)->get(route('maintenance.index'))
            ->assertOk()->assertDontSee('A private maintenance report.');
        $this->patch(route('owner.maintenance.update', $maintenance), ['status' => 'in_progress'])
            ->assertNotFound();
        $this->assertSame('open', $maintenance->fresh()->status);
    }

    private function bookingFor(User $owner, string $status = 'confirmed', ?string $reference = null): Booking
    {
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $space = Space::factory()->create(['property_id' => $property->id]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        return Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => $reference ?? 'SM-'.Str::upper((string) Str::ulid()),
            'occupants' => 1,
            'monthly_rent' => 10000,
            'start_date' => today()->subDay(),
            'end_date' => today()->addMonths(6),
            'status' => $status,
        ]);
    }

    private function payment(Booking $booking, array $attributes = []): Payment
    {
        return Payment::query()->create(array_replace([
            'booking_id' => $booking->id,
            'amount' => 10000,
            'paid_amount' => 0,
            'payment_type' => 'monthly_rent',
            'due_date' => today()->addDays(5),
            'payment_status' => 'pending',
        ], $attributes));
    }

    private function transaction(User $owner, Payment $payment, int $amount): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'payment_id' => $payment->id,
            'confirmed_by' => $owner->id,
            'receipt_number' => 'SMR-'.Str::upper((string) Str::ulid()),
            'amount' => $amount,
            'received_at' => now(),
            'payment_method' => 'cash',
        ]);
    }
}
