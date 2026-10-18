<?php

namespace Tests\Feature;

use App\Mail\VerificationCodeMail;
use App\Models\Amenity;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\RentalRequest;
use App\Models\Review;
use App\Models\Space;
use App\Models\User;
use App\Services\RentalTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Mail::fake();
        config(['otp.testing_email_delivery' => true]);
    }

    private function student(): User
    {
        return User::factory()->create(['role' => 'renter', 'is_student' => true]);
    }

    private function listing(array $attributes = []): Space
    {
        return Space::factory()->published()->create($attributes + [
            'property_id' => Property::factory()->published()->create(['city' => 'Cebu City'])->id,
            'student_friendly' => true, 'space_type' => 'private_room', 'nearby_school' => 'Cebu Test University Main',
            'campus_distance_km' => 1.5, 'furnishing' => 'furnished', 'price' => '5000.00',
            'deposit_amount' => '5000.00', 'advance_months' => 1, 'house_rules' => 'Quiet after 10 PM.',
        ]);
    }

    private function requestData(Space $space): array
    {
        return ['move_in_date' => today()->toDateString(), 'duration_months' => 1, 'occupants' => 1,
            'contact_phone' => '09171234567', 'terms_accepted' => '1',
            'terms_fingerprint' => RentalTerms::fingerprint($space), 'message' => 'I would like to rent this room.'];
    }

    public function test_student_registration_reuses_real_otp_and_preserves_renter_role(): void
    {
        $this->get('/register/student')->assertOk()->assertSee('School or university');
        $this->post('/register/student', [
            'name' => 'Student Test', 'email' => 'student@example.test', 'password' => 'Student-Password-2026!',
            'password_confirmation' => 'Student-Password-2026!', 'account_type' => 'admin',
            'school_or_campus' => 'Cebu Test University', 'campus' => 'Main', 'preferred_location' => 'Cebu City',
            'verification_method' => 'email', 'terms' => 1,
        ])->assertRedirect(route('verification.notice'));
        $student = User::where('email', 'student@example.test')->sole();
        $this->assertTrue($student->is_student);
        $this->assertSame('renter', $student->role);
        $this->assertTrue(Hash::check('Student-Password-2026!', $student->password));
        $this->assertSame('Main', $student->renterPreference->campus);
        $this->get(route('renter.dashboard'))->assertRedirect(route('verification.notice'));
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->post(route('verification.verify'), ['code' => '111'])->assertSessionHasErrors('code');
        $this->post(route('verification.verify'), ['code' => $code])->assertRedirect(route('renter.dashboard'));
        $this->assertNull($student->fresh()->verification_required_at);
        $this->post(route('verification.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->get(route('renter.dashboard'))->assertOk()->assertSee('Student renter workspace')->assertSee('Cebu Test University');
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $student->email, 'password' => 'Student-Password-2026!'])->assertRedirect(route('renter.dashboard'));
    }

    public function test_student_requires_school_and_does_not_unlock_when_delivery_unavailable(): void
    {
        $data = ['name' => 'Student', 'email' => 'new@example.test', 'password' => 'Student-Password-2026!',
            'password_confirmation' => 'Student-Password-2026!', 'verification_method' => 'email', 'terms' => 1];
        $this->post('/register/student', $data)->assertSessionHasErrors('school_or_campus');
        config(['otp.testing_email_delivery' => false, 'otp.email.enabled' => false]);
        $this->post('/register/student', $data + ['school_or_campus' => 'School'])->assertSessionHasErrors('verification_method');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_student_can_recover_account_without_changing_student_or_verification_records(): void
    {
        $student = $this->student();
        $token = Password::createToken($student);
        $this->post('/reset-password', ['token' => $token, 'email' => $student->email,
            'password' => 'Recovered-Password-2026!', 'password_confirmation' => 'Recovered-Password-2026!'])->assertRedirect('/login');
        $this->assertTrue($student->fresh()->is_student);
        $this->assertTrue(Hash::check('Recovered-Password-2026!', $student->fresh()->password));
        $this->assertNull($student->fresh()->verification_required_at);
    }

    public function test_preferences_persist_multiple_types_and_amenities_and_are_user_scoped(): void
    {
        $student = $this->student();
        $wifi = Amenity::firstOrCreate(['name' => 'Wi-Fi'], ['category' => 'Connectivity']);
        $this->actingAs($student)->put(route('renter.preferences.update'), [
            'is_student' => 1, 'school_or_campus' => 'Cebu Test University', 'campus' => 'Main',
            'budget_min' => 3000, 'budget_max' => 7000, 'preferred_location' => 'Cebu', 'max_distance_km' => 2,
            'preferred_types' => ['private_room', 'bedspace'], 'preferred_amenity_ids' => [$wifi->id],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['private_room', 'bedspace'], $student->fresh()->renterPreference->preferred_types);
        $this->assertSame([$wifi->id], $student->fresh()->renterPreference->preferred_amenity_ids);
        $other = $this->student();
        $this->actingAs($other)->get(route('renter.preferences.edit'))->assertDontSee('Cebu Test University');
        $this->put(route('renter.preferences.update'), ['is_student' => 1, 'school_or_campus' => 'Other',
            'preferred_types' => ['invalid'], 'preferred_amenity_ids' => [99999], 'max_distance_km' => -1])->assertSessionHasErrors(['preferred_types.0', 'preferred_amenity_ids.0', 'max_distance_km']);
    }

    public function test_school_distance_furnishing_type_location_price_and_amenities_intersect(): void
    {
        $space = $this->listing(['space_name' => 'Matching Study Room']);
        $wifi = Amenity::firstOrCreate(['name' => 'Wi-Fi'], ['category' => 'Connectivity']);
        $space->amenities()->attach($wifi);
        $other = $this->listing(['space_name' => 'No Reported Distance', 'campus_distance_km' => null]);
        $this->listing(['space_name' => 'Different Town', 'property_id' => Property::factory()->published()->create(['city' => 'Davao City'])->id]);
        $this->listing(['space_name' => 'Hidden Listing', 'listing_status' => 'draft']);
        $filters = ['student_friendly' => 1, 'school' => 'Cebu Test University', 'location' => 'Cebu City',
            'max_distance_km' => 2, 'min_price' => 4000, 'max_price' => 6000,
            'space_types' => ['private_room'], 'furnishing' => 'furnished', 'amenities' => [$wifi->id]];
        $this->get(route('properties.index', $filters))->assertOk()->assertSee('Matching Study Room')
            ->assertDontSee('No Reported Distance')->assertDontSee('Different Town')->assertDontSee('Hidden Listing')->assertSee('owner-reported');
        $this->get(route('properties.index', ['q' => 'Matching Study Room', 'location' => 'Davao City']))
            ->assertOk()->assertViewHas('spaces', fn ($spaces) => $spaces->isEmpty());
        $this->get(route('properties.index', ['max_distance_km' => 2]))->assertSessionHasErrors('school');
        $this->get(route('properties.show', $other->property))->assertOk()->assertDontSee('0.00 km');
    }

    public function test_recommendations_use_real_preferences_and_legacy_general_renter_remains_supported(): void
    {
        $student = $this->student();
        $student->renterPreference()->create(['school_or_campus' => 'Cebu Test University', 'budget_max' => 6000,
            'preferred_types' => ['private_room'], 'preferred_location' => 'Cebu City']);
        $match = $this->listing(['space_name' => 'Recommendation Match']);
        $this->listing(['space_name' => 'Over Budget', 'price' => 9000]);
        $this->actingAs($student)->get(route('renter.dashboard'))->assertOk()->assertSee('Recommendation Match')->assertDontSee('Over Budget');
        $general = User::factory()->create(['role' => 'renter']);
        $this->actingAs($general)->get(route('renter.dashboard'))->assertOk()->assertDontSee('Student renter workspace');
    }

    public function test_saved_compare_inquiries_and_duplicate_submission_reuse_shared_records(): void
    {
        $student = $this->student();
        $space = $this->listing();
        $this->actingAs($student)->post(route('renter.saved-spaces.store', $space))->assertRedirect();
        $this->post(route('renter.saved-spaces.store', $space))->assertRedirect();
        $this->assertSame(1, $student->savedSpaces()->count());
        $this->post(route('spaces.compare.store', $space))->assertRedirect();
        $this->get(route('spaces.compare.index'))->assertOk()->assertSee($space->space_name);
        $this->post(route('renter.inquiries.store', $space), ['message' => 'Is this room available for students?'])->assertRedirect();
        $this->post(route('renter.inquiries.store', $space), ['message' => 'Is this room available for students?'])->assertRedirect();
        $this->assertDatabaseCount('inquiries', 1);
        $this->assertDatabaseCount('inquiry_activities', 1);
        $inquiry = Inquiry::sole();
        $this->actingAs($space->property->owner)->post(route('owner.inquiries.reply', $inquiry), ['message' => 'Yes, please send your dates.'])->assertRedirect();
        $this->actingAs($student)->get(route('renter.inquiries.show', $inquiry))->assertOk()->assertSee('please send your dates');
        $this->actingAs($this->student())->get(route('renter.inquiries.show', $inquiry))->assertNotFound();
    }

    public function test_student_must_accept_exact_terms_and_owner_cannot_approve_changed_terms(): void
    {
        $student = $this->student();
        $space = $this->listing();
        $data = $this->requestData($space);
        $this->actingAs($student)->post(route('renter.requests.store', $space), array_diff_key($data, ['terms_accepted' => 1]))->assertSessionHasErrors('terms_accepted');
        $this->post(route('renter.requests.store', $space), [...$data, 'terms_fingerprint' => str_repeat('a', 64)])->assertSessionHasErrors('terms_accepted');
        $this->post(route('renter.requests.store', $space), $data)->assertSessionHasNoErrors()->assertRedirect();
        $rental = RentalRequest::sole();
        $this->assertNotNull($rental->terms_accepted_at);
        $space->update(['price' => '6000.00']);
        $this->actingAs($space->property->owner)->patch(route('owner.requests.update', $rental), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_connected_request_more_information_rejection_approval_booking_and_authorization(): void
    {
        $student = $this->student();
        $space = $this->listing();
        $owner = $space->property->owner;
        $this->actingAs($student)->post(route('renter.requests.store', $space), $this->requestData($space))->assertRedirect();
        $rental = RentalRequest::sole();
        $this->post(route('renter.requests.store', $space), $this->requestData($space))->assertSessionHasErrors('space');
        $this->patch(route('owner.requests.update', $rental), ['status' => 'approved'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->patch(route('owner.requests.update', $rental), ['status' => 'approved'])->assertNotFound();
        $this->actingAs($owner)->patch(route('owner.requests.update', $rental), ['status' => RentalRequest::STATUS_MORE_INFORMATION, 'response_message' => 'Please confirm your move-in plans.'])->assertRedirect();
        $this->patch(route('owner.requests.update', $rental), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->actingAs($student)->patch(route('renter.requests.respond', $rental), ['renter_response' => 'I can move in on the agreed date.'])->assertRedirect();
        $this->assertSame('pending', $rental->fresh()->status);
        $this->actingAs($owner)->patch(route('owner.requests.update', $rental), ['status' => 'rejected', 'response_message' => 'Please submit updated details.'])->assertRedirect();
        $this->assertDatabaseCount('bookings', 0);
        $this->actingAs($student)->post(route('renter.requests.store', $space), $this->requestData($space))->assertRedirect();
        $second = RentalRequest::latest('id')->first();
        $this->actingAs($owner)->patch(route('owner.requests.update', $second), ['status' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::sole();
        $this->assertSame('confirmed', $booking->status);
        $this->assertTrue($booking->payments()->exists());
        $this->assertSame(0.0, (float) $booking->payments()->sum('paid_amount'));
        $this->actingAs($student)->get(route('renter.dashboard'))
            ->assertOk()
            ->assertSee(route('bookings.show', $booking));
        $this->actingAs($student)->get(route('bookings.show', $booking))->assertOk()->assertSee('Terms accepted')->assertSee('Quiet after 10 PM.');
        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($this->student())->get(route('bookings.show', $booking))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('bookings.show', $booking))->assertOk();
        $this->assertGreaterThanOrEqual(3, $rental->statusHistory()->count());
    }

    public function test_terms_acceptance_survives_mysql_json_object_key_reordering(): void
    {
        $student = $this->student();
        $space = $this->listing();
        $this->actingAs($student)->post(route('renter.requests.store', $space), $this->requestData($space))->assertRedirect();
        $request = RentalRequest::sole();
        $request->update(['terms_snapshot' => array_reverse($request->terms_snapshot, true)]);
        $this->actingAs($space->property->owner)->patch(route('owner.requests.update', $request), ['status' => 'approved'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('bookings', 1);
    }

    private function activeBooking(User $student, Space $space): Booking
    {
        return Booking::create(['renter_id' => $student->id, 'space_id' => $space->id, 'reference' => 'TEST-'.uniqid(),
            'status' => 'active', 'start_date' => today()->subMonth(), 'end_date' => today(), 'monthly_rent' => 5000, 'occupants' => 1]);
    }

    public function test_active_maintenance_private_evidence_owner_resolution_completion_and_review(): void
    {
        Storage::fake('local');
        $student = $this->student();
        $space = $this->listing();
        $owner = $space->property->owner;
        $booking = $this->activeBooking($student, $space);
        $this->actingAs($student)->get(route('maintenance.index'))->assertOk()->assertSee('Report a maintenance issue');
        $data = ['booking_id' => $booking->id, 'description' => 'The sink is leaking and needs repair.', 'evidence' => UploadedFile::fake()->createWithContent('leak.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF")];
        $this->post(route('maintenance.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('maintenance.store'), $data)->assertRedirect();
        $this->assertDatabaseCount('maintenance_requests', 1);
        $maintenance = MaintenanceRequest::sole();
        Storage::disk('local')->assertExists($maintenance->evidence_path);
        $this->get(route('maintenance.evidence', $maintenance))->assertOk();
        $this->actingAs($this->student())->get(route('maintenance.evidence', $maintenance))->assertNotFound();
        $this->actingAs($owner)->patch(route('owner.maintenance.update', $maintenance), ['status' => 'resolved', 'notes' => 'Fixed.'])->assertSessionHasErrors('status');
        $this->patch(route('owner.maintenance.update', $maintenance), ['status' => 'in_progress'])->assertRedirect();
        $this->patch(route('owner.maintenance.update', $maintenance), ['status' => 'resolved', 'notes' => 'Replaced the leaking pipe.'])->assertRedirect();
        $this->assertSame(3, $maintenance->statusHistory()->count());
        $this->actingAs($student)->get(route('maintenance.index'))->assertOk()->assertSee('Replaced the leaking pipe.');
        $this->post(route('reviews.store', $booking), ['rating' => 5, 'comment' => 'Helpful owner.'])->assertSessionHasErrors('booking');
        $this->actingAs($owner)->patch(route('owner.bookings.update', $booking), ['status' => 'completed'])->assertRedirect();
        $this->actingAs($student)->post(route('reviews.store', $booking), ['rating' => 5, 'comment' => 'Helpful owner.'])->assertRedirect();
        $this->post(route('reviews.store', $booking), ['rating' => 4])->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('reviews', 1);
        $admin = User::factory()->create(['role' => 'admin']);
        $review = Review::sole();
        $this->patch(route('admin.reviews.moderate', $review), ['hidden' => 1, 'reason' => 'Not authorized.'])->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.reviews.moderate', $review), ['hidden' => 1, 'reason' => 'Requires moderation review.'])->assertRedirect();
        $this->get(route('properties.show', $space->property))->assertDontSee('Helpful owner.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'review_moderated']);
    }

    public function test_maintenance_rejects_foreign_inactive_bookings_and_executable_uploads(): void
    {
        Storage::fake('local');
        $student = $this->student();
        $booking = $this->activeBooking($student, $this->listing());
        $this->actingAs($this->student())->post(route('maintenance.store'), ['booking_id' => $booking->id, 'description' => 'A serious maintenance issue.'])->assertNotFound();
        $booking->update(['status' => 'confirmed']);
        $this->actingAs($student)->post(route('maintenance.store'), ['booking_id' => $booking->id, 'description' => 'A serious maintenance issue.'])->assertSessionHasErrors('booking_id');
        $booking->update(['status' => 'active']);
        $this->post(route('maintenance.store'), ['booking_id' => $booking->id, 'description' => 'A serious maintenance issue.', 'evidence' => UploadedFile::fake()->create('script.php', 1, 'text/x-php')])->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('maintenance_requests', 0);
    }

    public function test_owner_metadata_is_validated_and_other_owners_cannot_edit_it(): void
    {
        $space = $this->listing();
        $data = ['property_id' => $space->property_id, 'space_name' => $space->space_name,
            'space_type' => 'shared_room', 'price' => 5000, 'capacity' => 2,
            'availability_status' => 'available', 'student_friendly' => 1,
            'nearby_school' => 'Cebu Test University Main', 'campus_distance_km' => 1.25,
            'furnishing' => 'semi_furnished', 'transport_notes' => 'Owner reports a jeepney stop nearby.'];
        $this->actingAs($space->property->owner)->patch(route('owner.spaces.update', $space), $data)
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('shared_room', $space->fresh()->space_type);
        $this->get(route('properties.index', ['campus' => 'Main', 'transport' => 'jeepney']))
            ->assertOk()->assertViewHas('spaces', fn ($spaces) => $spaces->count() === 1);
        $this->patch(route('owner.spaces.update', $space), [...$data, 'nearby_school' => null])
            ->assertSessionHasErrors('nearby_school');
        $this->actingAs(User::factory()->create(['role' => 'owner']))->patch(route('owner.spaces.update', $space), $data)
            ->assertForbidden();
    }

    public function test_saved_move_in_preferences_respect_remaining_capacity_on_that_date(): void
    {
        $student = $this->student();
        $space = $this->listing(['capacity' => 2]);
        $this->activeBooking($this->student(), $space)->update(['end_date' => today()->addMonth()]);
        $student->renterPreference()->create(['school_or_campus' => 'Cebu Test University',
            'campus' => 'Main', 'move_in_date' => today()->addDay(), 'capacity' => 2]);
        $this->actingAs($student)->get(route('renter.dashboard'))->assertOk()
            ->assertViewHas('availableSpaces', fn ($spaces) => $spaces->isEmpty());
        $this->get(route('properties.index', ['available_from' => today()->addDay()->toDateString(), 'capacity' => 2]))
            ->assertOk()->assertViewHas('spaces', fn ($spaces) => $spaces->isEmpty());
    }

    public function test_student_school_content_is_escaped_in_dashboard(): void
    {
        $student = $this->student();
        $student->renterPreference()->create(['school_or_campus' => '<script>alert(1)</script>']);
        $this->actingAs($student)->get(route('renter.dashboard'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_partial_payment_balance_and_empty_states_are_real_and_scoped(): void
    {
        $student = $this->student();
        $booking = $this->activeBooking($student, $this->listing());
        Payment::create(['booking_id' => $booking->id, 'payment_type' => 'monthly_rent', 'amount' => 5000, 'paid_amount' => 2000,
            'payment_status' => 'partially_paid', 'due_date' => today()]);
        $this->actingAs($student)->get(route('renter.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['outstanding_balances'] === 3000.0);
        $this->actingAs($this->student())->get(route('renter.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => (float) $stats['outstanding_balances'] === 0.0)->assertSee('No recent inquiries yet');
    }
}
