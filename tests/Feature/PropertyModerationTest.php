<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_deactivate_property_with_an_audit_trail_and_restore_it(): void
    {
        $property = Property::factory()->published()->create();
        Space::factory()->published()->create(['property_id' => $property->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $reason = 'Listing contains inaccurate location information.';

        $this->actingAs($admin)->patch('/admin/properties/'.$property->id.'/status', ['status' => 'inactive', 'reason' => $reason])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('inactive', $property->fresh()->status);
        $this->assertNotNull($property->fresh()->moderated_at);
        $this->assertEquals($property->owner_id, $property->fresh()->owner_id);

        $log = AuditLog::query()->where('user_id', $admin->id)->latest('id')->firstOrFail();
        $details = json_decode($log->description, true, flags: JSON_THROW_ON_ERROR);
        $this->assertEquals($property->id, $details['property_id']);
        $this->assertSame('active', $details['from']);
        $this->assertSame('inactive', $details['to']);
        $this->assertSame($reason, $details['reason']);
        $this->get('/properties/'.$property->id)->assertNotFound();

        $this->patch('/admin/properties/'.$property->id.'/status', [
            'status' => 'active', 'reason' => 'Location information has been verified and corrected.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('active', $property->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_administrative_status_change_requires_valid_status_and_substantive_reason(): void
    {
        $property = Property::factory()->create();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->patch('/admin/properties/'.$property->id.'/status', ['status' => 'archived', 'reason' => 'short'])
            ->assertSessionHasErrors(['status', 'reason']);
        $this->patch('/admin/properties/'.$property->id.'/status', ['status' => 'inactive'])
            ->assertSessionHasErrors('reason');
        $this->assertSame('active', $property->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_cannot_clear_administrative_deactivation_through_update_status_or_publication(): void
    {
        $property = Property::factory()->published()->create([
            'status' => 'inactive', 'moderated_at' => now(), 'moderation_reason' => 'Temporarily deactivated for listing review.',
        ]);
        Space::factory()->published()->create(['property_id' => $property->id]);
        $moderatedAt = $property->moderated_at;
        $this->actingAs($property->owner);

        $this->put('/owner/properties/'.$property->id, [
            'property_name' => 'Owner Corrected Name', 'property_type' => 'apartment',
            'address' => '12 Luna Street', 'city' => 'Cebu City', 'province' => 'Cebu',
            'status' => 'active', 'moderated_at' => null, 'moderation_reason' => null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch('/owner/properties/'.$property->id.'/status', ['status' => 'active']);
        $this->patch('/owner/properties/'.$property->id.'/publish', ['listing_status' => 'published']);

        $this->assertSame('inactive', $property->fresh()->status);
        $this->assertEquals($moderatedAt, $property->fresh()->moderated_at);
        $this->assertSame('Temporarily deactivated for listing review.', $property->fresh()->moderation_reason);
        $this->get('/properties/'.$property->id)->assertNotFound();
    }

    public function test_only_administrators_can_view_oversight_and_change_moderation_status(): void
    {
        $property = Property::factory()->create();
        $this->get('/admin/properties')->assertRedirect('/login');

        foreach ([User::ROLE_OWNER, User::ROLE_RENTER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get('/admin/properties')->assertForbidden();
            $this->get('/admin/properties/'.$property->id)->assertForbidden();
            $this->patch('/admin/properties/'.$property->id.'/status', ['status' => 'inactive', 'reason' => 'Attempt to deactivate someone else listing.'])
                ->assertForbidden();
        }
        $this->assertSame('active', $property->fresh()->status);
    }

    public function test_administrative_overview_filters_by_owner_and_name(): void
    {
        $first = Property::factory()->create(['property_name' => 'Ocean Owner Property']);
        $second = Property::factory()->create(['property_name' => 'Mountain Owner Property']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/properties?owner_id='.$first->owner_id)->assertOk()
            ->assertViewHas('properties', fn ($properties) => $properties->pluck('id')->all() === [$first->id]);
        $this->get('/admin/properties?q=Mountain')->assertOk()
            ->assertViewHas('properties', fn ($properties) => $properties->pluck('id')->all() === [$second->id]);
        $this->get('/admin/properties/'.$first->id)->assertOk()->assertSee($first->property_name);
    }

    public function test_administrator_dashboard_counts_platform_listings_and_property_statuses(): void
    {
        Property::factory()->create(['status' => 'active']);
        Property::factory()->create(['status' => 'available']);
        Property::factory()->create(['status' => 'inactive']);
        Property::factory()->archived()->create();
        $property = Property::query()->firstOrFail();
        Space::factory()->published()->count(2)->create(['property_id' => $property->id]);
        Space::factory()->create(['property_id' => $property->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertViewHas('stats', function ($stats) {
            return $stats['total_properties'] === 4
                && $stats['total_spaces'] === 3
                && $stats['total_listings'] === 2
                && $stats['active_properties'] === 2
                && $stats['inactive_properties'] === 1;
        });
    }
}
