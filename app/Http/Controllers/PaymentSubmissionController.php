<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PaymentSubmissionController extends Controller
{
    public function store(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->hasRole(User::ROLE_RENTER), 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'gcash', 'maya', 'other'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'evidence' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ]);

        $disk = Storage::disk('local');
        $path = $request->file('evidence')->store('payment-evidence/'.$request->user()->id, 'local');
        if (! $path) {
            throw new RuntimeException('Payment evidence could not be stored.');
        }

        try {
            DB::transaction(function () use ($request, $payment, $data, $path): void {
                $ownedPayment = Payment::query()
                    ->whereKey($payment->id)
                    ->whereHas('booking', fn ($booking) => $booking->where('renter_id', $request->user()->id))
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! in_array($ownedPayment->payment_status, ['pending', 'partially_paid'], true)) {
                    throw ValidationException::withMessages(['payment' => 'This payment obligation cannot accept a new submission.']);
                }

                if ($ownedPayment->submissions()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['payment' => 'A payment submission is already awaiting review.']);
                }

                $amountCents = (int) round((float) $data['amount'] * 100);
                $balanceCents = (int) round($ownedPayment->outstanding_balance * 100);
                if ($amountCents < 1 || $amountCents > $balanceCents) {
                    throw ValidationException::withMessages(['amount' => 'The submitted amount must not exceed the outstanding balance.']);
                }

                $submission = $ownedPayment->submissions()->create([
                    'submitted_by' => $request->user()->id,
                    'amount' => number_format($amountCents / 100, 2, '.', ''),
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'] ?? null,
                    'evidence_path' => $path,
                ]);

                $ownedPayment->booking->space->property->owner->notify(new RentalWorkflowNotification(
                    'Payment proof submitted',
                    'A renter submitted proof of payment for '.$ownedPayment->booking->space->space_name.'. Review it before recording funds received.',
                    route('owner.payments.submissions.index'),
                ));
            });
        } catch (Throwable $exception) {
            if (! $disk->delete($path)) {
                Log::warning('Payment evidence cleanup failed after submission error.', ['path' => $path]);
            }

            throw $exception;
        }

        return back()->with('status', 'Your payment proof was sent to the property owner for review. Your balance will change only after approval.');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasRole(User::ROLE_OWNER) || $user->isAdministrator(), 403);

        $query = PaymentSubmission::query()
            ->with(['submitter', 'payment.booking.renter', 'payment.booking.space.property'])
            ->whereHas('payment.booking', fn ($booking) => $booking->visibleTo($user))
            ->latest();

        if ($request->input('status') && in_array($request->input('status'), ['pending', 'verified', 'rejected'], true)) {
            $query->where('status', $request->input('status'));
        }

        return view('payments.submissions', ['submissions' => $query->paginate(20)->withQueryString()]);
    }

    public function review(Request $request, PaymentSubmission $submission): RedirectResponse
    {
        $reviewer = $request->user();
        abort_unless($reviewer->hasRole(User::ROLE_OWNER) || $reviewer->isAdministrator(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verified', 'rejected'])],
            'rejection_reason' => ['nullable', 'required_if:decision,rejected', 'string', 'max:1000'],
        ]);

        $transaction = null;
        $submission = DB::transaction(function () use ($reviewer, $submission, $data, &$transaction): PaymentSubmission {
            $locked = PaymentSubmission::query()
                ->whereKey($submission->id)
                ->whereHas('payment.booking', fn ($booking) => $booking->visibleTo($reviewer))
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This payment submission has already been reviewed.']);
            }

            $payment = Payment::query()->whereKey($locked->payment_id)->lockForUpdate()->firstOrFail();
            $changes = [
                'status' => $data['decision'],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $data['decision'] === 'rejected' ? $data['rejection_reason'] : null,
            ];

            if ($data['decision'] === 'verified') {
                if (! in_array($payment->payment_status, ['pending', 'partially_paid'], true)
                    || (int) round((float) $locked->amount * 100) > (int) round($payment->outstanding_balance * 100)) {
                    throw ValidationException::withMessages(['decision' => 'The outstanding balance changed. Review the payment record before approving this submission.']);
                }

                $newPaidCents = (int) round((float) $payment->paid_amount * 100) + (int) round((float) $locked->amount * 100);
                $totalCents = (int) round((float) $payment->amount * 100);
                $payment->update([
                    'payment_date' => $locked->payment_date,
                    'payment_method' => $locked->payment_method,
                    'reference' => $locked->reference,
                    'paid_amount' => number_format($newPaidCents / 100, 2, '.', ''),
                    'payment_status' => $newPaidCents >= $totalCents ? 'paid' : 'partially_paid',
                    'confirmed_by' => $reviewer->id,
                ]);

                $transaction = PaymentTransaction::query()->create([
                    'payment_id' => $payment->id,
                    'confirmed_by' => $reviewer->id,
                    'receipt_number' => 'SMR-'.Str::upper((string) Str::ulid()),
                    'amount' => $locked->amount,
                    'received_at' => $locked->payment_date,
                    'payment_method' => $locked->payment_method,
                    'reference' => $locked->reference,
                ]);
            }

            $locked->update($changes);
            AuditLog::query()->create([
                'user_id' => $reviewer->id,
                'action' => 'payment_submission_'.$data['decision'],
                'description' => json_encode([
                    'payment_submission_id' => $locked->id,
                    'payment_id' => $payment->id,
                    'amount' => $locked->amount,
                    'reason' => $changes['rejection_reason'],
                ], JSON_THROW_ON_ERROR),
            ]);

            return $locked->load('submitter', 'payment.booking.renter', 'payment.booking.space.property.owner');
        });

        $renter = $submission->payment->booking->renter;
        $verified = $submission->status === 'verified';
        $renter->notify(new RentalWorkflowNotification(
            $verified ? 'Payment verified' : 'Payment submission not approved',
            $verified
                ? 'Your payment of ₱'.number_format((float) $submission->amount, 2).' was verified and recorded.'
                : 'Your payment submission was not approved. Reason: '.$submission->rejection_reason,
            $verified ? route('payments.receipts.show', $transaction) : route('payments.index'),
        ));

        return back()->with('status', $verified ? 'Payment proof verified and the payment ledger was updated.' : 'Payment submission rejected with a recorded reason.');
    }

    public function evidence(Request $request, PaymentSubmission $submission)
    {
        $user = $request->user();
        $submission->loadMissing('payment.booking.space.property');
        $isSubmitter = $submission->submitted_by === $user->id;
        $isOwner = $user->hasRole(User::ROLE_OWNER)
            && $submission->payment->booking->space->property->owner_id === $user->id;
        abort_unless($isSubmitter || $isOwner || $user->isAdministrator(), 404);

        $prefix = 'payment-evidence/'.$submission->submitted_by.'/';
        abort_unless(str_starts_with($submission->evidence_path, $prefix), 404);
        $extension = strtolower(pathinfo($submission->evidence_path, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($submission->evidence_path), 404);

        return $disk->download($submission->evidence_path, 'payment-proof-'.$submission->id.'.'.$extension);
    }
}
