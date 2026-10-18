<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\InquiryActivity;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use App\Notifications\InquiryActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_can_contact_owner_and_owner_reply_is_private_and_notifies_the_renter(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        $this->actingAs($renter)->post(route('renter.inquiries.store', $space), [
            'message' => 'Is this space available for a student next month?',
        ])->assertRedirect();

        $inquiry = Inquiry::query()->firstOrFail();
        $this->assertSame($renter->id, $inquiry->renter_id);
        $this->assertSame($owner->id, $inquiry->owner_id);
        $this->assertSame('open', $inquiry->status);
        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'user_id' => $renter->id,
            'activity_type' => 'message',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
            'type' => InquiryActivityNotification::class,
        ]);

        $this->actingAs($owner)->get(route('owner.inquiries.show', $inquiry))->assertOk()
            ->assertSee($renter->name)->assertSee('Is this space available for a student next month?');
        $this->assertNotNull($inquiry->activities()->where('user_id', $renter->id)->firstOrFail()->read_at);

        $this->post(route('owner.inquiries.reply', $inquiry), [
            'message' => 'Yes, the owner can discuss a move-in date with you.',
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $renter->id,
            'type' => InquiryActivityNotification::class,
        ]);
        $this->actingAs($renter)->get(route('renter.inquiries.show', $inquiry))->assertOk()
            ->assertSee('Yes, the owner can discuss a move-in date with you.');
    }

    public function test_inquiries_are_scoped_to_participants_and_role_routes(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $inquiry = Inquiry::query()->create([
            'space_id' => $space->id,
            'renter_id' => $renter->id,
            'owner_id' => $owner->id,
            'status' => 'open',
        ]);
        InquiryActivity::query()->create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $renter->id,
            'activity_type' => 'message',
            'body' => 'Private student contact message.',
        ]);

        $this->actingAs($otherRenter)->get(route('renter.inquiries.show', $inquiry))->assertNotFound();
        $this->post(route('renter.inquiries.reply', $inquiry), ['message' => 'Unauthorized reply attempt.'])->assertNotFound();
        $this->actingAs($otherOwner)->get(route('owner.inquiries.show', $inquiry))->assertNotFound();
        $this->post(route('owner.inquiries.reply', $inquiry), ['message' => 'Unauthorized reply attempt.'])->assertNotFound();
        $this->actingAs($renter)->get(route('owner.inquiries.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('renter.inquiries.index'))->assertForbidden();
    }

    public function test_inquiry_creation_requires_a_published_available_space_and_valid_message(): void
    {
        [$space] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($renter)->post(route('renter.inquiries.store', $space), ['message' => 'Too short'])
            ->assertSessionHasErrors('message');
        $this->assertDatabaseCount('inquiries', 0);

        $unavailable = $this->publishedSpace('occupied');
        $this->post(route('renter.inquiries.store', $unavailable[0]), [
            'message' => 'Can you tell me if this space might be available later?',
        ])->assertNotFound();
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_property_detail_offers_inquiries_only_to_verified_renters_for_available_spaces(): void
    {
        [$available] = $this->publishedSpace();
        $occupied = Space::factory()->published()->create([
            'property_id' => $available->property_id,
            'availability_status' => 'occupied',
        ]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);

        $this->actingAs($renter)->get(route('properties.show', $available->property_id))->assertOk()
            ->assertSee('Ask the owner about this space')
            ->assertSee('name="message"', false);
        $this->get(route('properties.show', $available->property_id))
            ->assertSee('inquiry-space-'.$available->id)
            ->assertDontSee('inquiry-space-'.$occupied->id);

        $unverified = User::factory()->create([
            'role' => User::ROLE_RENTER,
            'verification_required_at' => now(),
        ]);
        $this->actingAs($unverified)->get(route('properties.show', $available->property_id))->assertOk()
            ->assertDontSee('Ask the owner about this space');
    }

    public function test_owner_can_close_an_inquiry_and_closed_inquiries_cannot_be_replied_to(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $inquiry = Inquiry::query()->create([
            'space_id' => $space->id,
            'renter_id' => $renter->id,
            'owner_id' => $owner->id,
            'status' => 'open',
        ]);

        $this->actingAs($owner)->patch(route('owner.inquiries.close', $inquiry))->assertRedirect();
        $this->assertSame('closed', $inquiry->fresh()->status);
        $this->assertDatabaseHas('inquiry_activities', [
            'inquiry_id' => $inquiry->id,
            'activity_type' => 'status_changed',
            'from_status' => 'open',
            'to_status' => 'closed',
        ]);
        $this->post(route('owner.inquiries.reply', $inquiry), ['message' => 'This reply should not be accepted.'])
            ->assertSessionHasErrors('message');
        $this->assertDatabaseCount('inquiry_activities', 1);
    }

    public function test_live_updates_are_private_mark_incoming_messages_read_and_prevent_duplicate_replies(): void
    {
        [$space, $owner] = $this->publishedSpace();
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $inquiry = Inquiry::query()->create([
            'space_id' => $space->id,
            'renter_id' => $renter->id,
            'owner_id' => $owner->id,
            'status' => 'open',
        ]);
        $activity = $inquiry->activities()->create([
            'user_id' => $renter->id,
            'activity_type' => 'message',
            'body' => '<script>owner supplied text</script>',
        ]);
        $owner->notify(new InquiryActivityNotification($inquiry->load('space'), 'New reply to your inquiry'));

        $updatesUrl = route('owner.inquiries.updates', $inquiry);
        $response = $this->actingAs($owner)->getJson($updatesUrl.'?after_id=0')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('activities.0.id', $activity->id)
            ->assertJsonPath('activities.0.body', '<script>owner supplied text</script>')
            ->assertJsonPath('activities.0.user_name', $renter->name)
            ->assertJsonPath('unread_inquiries', 0);
        $this->assertNotNull($activity->fresh()->read_at);
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());

        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($otherOwner)->getJson($updatesUrl)->assertNotFound();

        $reply = ['message' => 'I would like to schedule a viewing this week.'];
        $this->actingAs($renter)->postJson(route('renter.inquiries.reply', $inquiry), $reply)
            ->assertCreated()->assertJsonPath('sent', true);
        $this->postJson(route('renter.inquiries.reply', $inquiry), $reply)
            ->assertOk()->assertJsonPath('sent', false);
        $this->assertSame(2, $inquiry->activities()->count());
    }

    private function publishedSpace(string $availability = 'available'): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $property = Property::factory()->published()->create(['owner_id' => $owner->id]);
        $space = Space::factory()->published()->create([
            'property_id' => $property->id,
            'availability_status' => $availability,
        ]);

        return [$space, $owner];
    }
}
