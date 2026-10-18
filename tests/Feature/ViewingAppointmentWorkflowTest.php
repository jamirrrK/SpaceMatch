<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Models\ViewingAppointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViewingAppointmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_requests_viewing_owner_accepts_and_notification_is_created(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $requestedTime = now()->addDays(2)->setTime(14, 0)->format('Y-m-d\TH:i');

        $this->actingAs($renter)->post(route('renter.viewings.request', $space), [
            'requested_start_at' => $requestedTime,
        ])->assertRedirect();

        $appointment = ViewingAppointment::query()->firstOrFail();
        $this->assertSame('pending', $appointment->status);
        $this->assertSame($owner->id, $appointment->owner_id);

        $this->actingAs($owner)->patch(route('owner.viewings.accept', $appointment))->assertRedirect();
        $this->assertSame('scheduled', $appointment->fresh()->status);
        $this->assertNotNull($appointment->fresh()->scheduled_start_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $renter->id]);
        $this->actingAs($renter)->get(route('renter.viewings.show', $appointment))->assertOk()
            ->assertSee('One hour')->assertSee('Status history');
    }

    public function test_owner_cannot_double_schedule_an_overlapping_viewing_and_can_propose_an_alternative(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $firstRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $secondRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $requestedAt = now()->addDays(2)->setTime(14, 0);
        $first = $this->appointment($space, $firstRenter, $owner, $requestedAt);
        $second = $this->appointment($space, $secondRenter, $owner, $requestedAt);

        $this->actingAs($owner)->patch(route('owner.viewings.accept', $first))->assertRedirect();
        $this->patch(route('owner.viewings.accept', $second))->assertSessionHasErrors('scheduled_start_at');
        $this->assertSame('pending', $second->fresh()->status);

        $newTime = $requestedAt->copy()->addHours(2)->format('Y-m-d\TH:i');
        $this->put(route('owner.viewings.reschedule', $second), [
            'scheduled_start_at' => $newTime,
            'reason' => 'I have another appointment at the time you requested.',
        ])->assertRedirect();
        $this->assertSame('rescheduled', $second->fresh()->status);

        $this->actingAs($secondRenter)->patch(route('renter.viewings.confirm', $second))->assertRedirect();
        $this->assertSame('scheduled', $second->fresh()->status);
        $this->assertEquals($newTime, $second->fresh()->scheduled_start_at->format('Y-m-d\TH:i'));
    }

    public function test_viewing_decisions_validate_reasons_and_participant_access(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $appointment = $this->appointment($space, $renter, $owner, now()->addDays(3));

        $this->actingAs($otherRenter)->get(route('renter.viewings.show', $appointment))->assertNotFound();
        $this->actingAs($owner);
        $this->patch(route('owner.viewings.decline', $appointment), ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->patch(route('owner.viewings.decline', $appointment), [
            'reason' => 'The space cannot be viewed at that time.',
        ])->assertRedirect();
        $this->assertSame('declined', $appointment->fresh()->status);

        $this->actingAs($renter)->post(route('renter.viewings.request', $space), [
            'requested_start_at' => now()->subDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('requested_start_at');
        $this->assertDatabaseCount('viewing_appointments', 1);
    }

    private function publishedSpace(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $property = Property::factory()->published()->create(['owner_id' => $owner->id]);
        $space = Space::factory()->published()->create(['property_id' => $property->id]);

        return [$space, $owner];
    }

    private function appointment(Space $space, User $renter, User $owner, $start): ViewingAppointment
    {
        return ViewingAppointment::query()->create([
            'space_id' => $space->id,
            'renter_id' => $renter->id,
            'owner_id' => $owner->id,
            'requested_start_at' => $start,
            'status' => 'pending',
        ]);
    }
}
