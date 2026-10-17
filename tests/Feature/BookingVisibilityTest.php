<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_records_are_scoped_to_the_renter_or_owner_and_rendered_from_database(): void
    {
        $firstOwner = User::factory()->create(['name' => 'First Owner', 'role' => User::ROLE_OWNER]);
        $secondOwner = User::factory()->create(['name' => 'Second Owner', 'role' => User::ROLE_OWNER]);
        $firstRenter = User::factory()->create(['name' => 'First Renter', 'role' => User::ROLE_RENTER]);
        $secondRenter = User::factory()->create(['name' => 'Second Renter', 'role' => User::ROLE_RENTER]);

        $firstProperty = Property::factory()->create(['owner_id' => $firstOwner->id]);
        $secondProperty = Property::factory()->create(['owner_id' => $secondOwner->id]);
        $firstSpace = Space::factory()->create(['property_id' => $firstProperty->id, 'space_name' => 'First Studio']);
        $secondSpace = Space::factory()->create(['property_id' => $secondProperty->id, 'space_name' => 'Second Studio']);

        $firstBooking = Booking::query()->create([
            'renter_id' => $firstRenter->id,
            'space_id' => $firstSpace->id,
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-15',
            'status' => 'pending',
        ]);
        $secondBooking = Booking::query()->create([
            'renter_id' => $secondRenter->id,
            'space_id' => $secondSpace->id,
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-25',
            'status' => 'confirmed',
        ]);
        Payment::query()->create([
            'booking_id' => $firstBooking->id,
            'amount' => 1200,
            'payment_method' => 'bank transfer',
            'payment_status' => 'paid',
        ]);
        Payment::query()->create([
            'booking_id' => $secondBooking->id,
            'amount' => 2400,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);
        MaintenanceRequest::query()->create([
            'booking_id' => $firstBooking->id,
            'description' => 'First booking repair note',
            'status' => 'open',
        ]);
        MaintenanceRequest::query()->create([
            'booking_id' => $secondBooking->id,
            'description' => 'Second booking repair note',
            'status' => 'resolved',
        ]);
        Review::query()->create([
            'booking_id' => $firstBooking->id,
            'rating' => 5,
            'comment' => 'First renter review note',
        ]);
        Review::query()->create([
            'booking_id' => $secondBooking->id,
            'rating' => 4,
            'comment' => 'Second renter review note',
        ]);

        $this->actingAs($firstRenter)->get(route('bookings.index'))->assertOk()
            ->assertSee('First Studio')->assertSee('First Renter')
            ->assertDontSee('Second Studio')->assertDontSee('Second Renter');

        $this->actingAs($firstOwner)->get(route('bookings.index'))->assertOk()
            ->assertSee('First Studio')->assertSee('First Renter')
            ->assertDontSee('Second Studio')->assertDontSee('Second Renter');

        $this->actingAs($firstRenter)->get(route('payments.index'))->assertOk()
            ->assertSee('First Studio')->assertDontSee('Second Studio');
        $this->get(route('maintenance.index'))->assertOk()
            ->assertSee('First booking repair note')->assertDontSee('Second booking repair note');
        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('First renter review note')->assertDontSee('Second renter review note');

        $this->actingAs($firstOwner)->get(route('payments.index'))->assertOk()
            ->assertSee('First Studio')->assertDontSee('Second Studio');
        $this->get(route('maintenance.index'))->assertOk()
            ->assertSee('First booking repair note')->assertDontSee('Second booking repair note');
        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('First renter review note')->assertDontSee('Second renter review note');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('bookings.index'))->assertOk()
            ->assertSee('First Studio')->assertSee('Second Studio');
        $this->get(route('payments.index'))->assertOk()->assertSee('First Studio')->assertSee('Second Studio');
        $this->get(route('maintenance.index'))->assertOk()
            ->assertSee('First booking repair note')->assertSee('Second booking repair note');
        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('First renter review note')->assertSee('Second renter review note');
    }
}