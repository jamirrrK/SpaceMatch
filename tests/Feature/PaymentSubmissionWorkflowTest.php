<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentSubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_renter_submission_stays_unpaid_until_owner_verifies_proof(): void
    {
        Storage::fake('local');
        [$payment, $renter, $owner] = $this->paymentForRenter();

        $this->actingAs($renter)->get(route('payments.index'))
            ->assertOk()->assertSee('Submit proof of payment');
        $this->actingAs($renter)->post(route('renter.payments.submissions.store', $payment), $this->submissionData())
            ->assertSessionHasNoErrors();
        $submission = PaymentSubmission::query()->sole();

        $this->assertSame('pending', $submission->status);
        $this->assertSame(0.0, (float) $payment->fresh()->paid_amount);
        Storage::disk('local')->assertExists($submission->evidence_path);
        $this->actingAs($renter)->get(route('payments.index'))
            ->assertOk()->assertSee('Your proof is waiting for the property owner to review it.')
            ->assertSee('Proof Pending');

        $this->post(route('renter.payments.submissions.store', $payment), $this->submissionData())
            ->assertSessionHasErrors('payment');
        $otherRenter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $this->actingAs($otherRenter)->post(route('renter.payments.submissions.store', $payment), $this->submissionData())
            ->assertNotFound();
        $this->actingAs($otherRenter)->get(route('payments.submissions.evidence', $submission))->assertNotFound();

        $this->actingAs($owner)->get(route('owner.payments.submissions.index'))
            ->assertOk()->assertSee($renter->name)->assertSee('Open payment proof');
        $this->patch(route('owner.payments.confirm', $payment), [
            'payment_date' => today()->toDateString(),
            'payment_method' => 'cash',
            'amount' => '600.00',
        ])->assertSessionHasErrors('payment');
        $this->get(route('payments.submissions.evidence', $submission))
            ->assertDownload('payment-proof-'.$submission->id.'.pdf');

        $this->patch(route('owner.payments.submissions.review', $submission), [
            'decision' => 'rejected',
            'rejection_reason' => 'The receipt does not show a matching transfer.',
        ])->assertRedirect();
        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertSame(0.0, (float) $payment->fresh()->paid_amount);
        $this->assertDatabaseCount('payment_transactions', 0);

        $this->actingAs($renter)->post(route('renter.payments.submissions.store', $payment), $this->submissionData())
            ->assertSessionHasNoErrors();
        $replacement = PaymentSubmission::query()->latest('id')->firstOrFail();
        $this->actingAs($owner)->patch(route('owner.payments.submissions.review', $replacement), [
            'decision' => 'verified',
        ])->assertRedirect();

        $this->assertSame('verified', $replacement->fresh()->status);
        $this->assertSame(600.0, (float) $payment->fresh()->paid_amount);
        $this->assertSame('partially_paid', $payment->fresh()->payment_status);
        $this->assertDatabaseHas('payment_transactions', [
            'payment_id' => $payment->id,
            'confirmed_by' => $owner->id,
            'amount' => '600.00',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'payment_submission_verified',
        ]);
        $this->assertSame(1, PaymentTransaction::query()->where('payment_id', $payment->id)->count());
        $this->assertSame(2, AuditLog::query()->count());
    }

    public function test_payment_proof_requires_safe_files_valid_amount_and_owner_scoped_review(): void
    {
        Storage::fake('local');
        [$payment, $renter, $owner] = $this->paymentForRenter();
        $data = $this->submissionData();
        $data['evidence'] = UploadedFile::fake()->create('payload.php', 1, 'application/x-php');
        $this->actingAs($renter)->post(route('renter.payments.submissions.store', $payment), $data)
            ->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('payment_submissions', 0);

        $overpayment = $this->submissionData();
        $overpayment['amount'] = '2500.00';
        $this->post(route('renter.payments.submissions.store', $payment), $overpayment)->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('payment_submissions', 0);

        $validData = $this->submissionData();
        $this->post(route('renter.payments.submissions.store', $payment), $validData)->assertSessionHasNoErrors();
        $submission = PaymentSubmission::query()->sole();
        $otherOwner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->actingAs($otherOwner)->patch(route('owner.payments.submissions.review', $submission), [
            'decision' => 'verified',
        ])->assertNotFound();
        $this->assertSame('pending', $submission->fresh()->status);

        $this->actingAs($owner)->patch(route('owner.payments.submissions.review', $submission), [
            'decision' => 'rejected',
        ])->assertSessionHasErrors('rejection_reason');
        $this->assertSame('pending', $submission->fresh()->status);
    }

    public function test_administrator_can_review_payment_proof_without_becoming_the_booking_owner(): void
    {
        Storage::fake('local');
        [$payment, $renter] = $this->paymentForRenter();
        $this->actingAs($renter)->post(route('renter.payments.submissions.store', $payment), $this->submissionData())
            ->assertSessionHasNoErrors();
        $submission = PaymentSubmission::query()->sole();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('admin.payments.submissions.index'))
            ->assertOk()->assertSee('Open payment proof');
        $this->patch(route('admin.payments.submissions.review', $submission), ['decision' => 'verified'])
            ->assertRedirect();

        $this->assertSame('verified', $submission->fresh()->status);
        $this->assertSame($admin->id, $submission->fresh()->reviewed_by);
        $this->assertSame(600.0, (float) $payment->fresh()->paid_amount);
    }

    private function paymentForRenter(): array
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $renter = User::factory()->create(['role' => User::ROLE_RENTER]);
        $property = Property::factory()->published()->create(['owner_id' => $owner->id]);
        $space = Space::factory()->published()->create(['property_id' => $property->id]);
        $booking = Booking::query()->create([
            'renter_id' => $renter->id,
            'space_id' => $space->id,
            'reference' => 'SM-'.fake()->unique()->numerify('########'),
            'start_date' => today(),
            'end_date' => today()->addMonth(),
            'status' => 'active',
            'monthly_rent' => 2000,
        ]);
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'amount' => 2000,
            'payment_type' => 'monthly_rent',
            'payment_status' => 'pending',
            'paid_amount' => 0,
            'due_date' => today(),
        ]);

        return [$payment, $renter, $owner];
    }

    private function submissionData(): array
    {
        return [
            'amount' => '600.00',
            'payment_date' => today()->toDateString(),
            'payment_method' => 'gcash',
            'reference' => 'GCASH-123456',
            'evidence' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf'),
        ];
    }
}
