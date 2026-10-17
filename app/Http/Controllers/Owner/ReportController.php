<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Property;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $ownerId = $request->user()->id;
        $transactions = $this->transactions($ownerId, $filters);
        $obligations = $this->obligations($ownerId, $filters);
        $outstandingBalance = (clone $obligations)->whereRaw('payments.amount > payments.paid_amount')
            ->sum(DB::raw('payments.amount - payments.paid_amount'));
        $overdueBalance = (clone $obligations)->whereRaw('payments.amount > payments.paid_amount')
            ->whereDate('payments.due_date', '<', today())
            ->sum(DB::raw('payments.amount - payments.paid_amount'));

        $propertyBalances = (clone $obligations)
            ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
            ->join('spaces', 'bookings.space_id', '=', 'spaces.id')
            ->join('properties', 'spaces.property_id', '=', 'properties.id')
            ->select('properties.id', 'properties.property_name')
            ->selectRaw('SUM(CASE WHEN payments.amount > payments.paid_amount THEN payments.amount - payments.paid_amount ELSE 0 END) as outstanding_balance')
            ->groupBy('properties.id', 'properties.property_name')
            ->orderBy('properties.property_name')
            ->get();

        return view('owner.reports.index', [
            'filters' => $filters,
            'properties' => Property::query()->where('owner_id', $ownerId)->orderBy('property_name')->get(['id', 'property_name']),
            'collectionTotal' => (clone $transactions)->sum('amount'),
            'collectionCount' => (clone $transactions)->count(),
            'outstandingBalance' => $outstandingBalance,
            'overdueBalance' => $overdueBalance,
            'propertyBalances' => $propertyBalances,
            'transactions' => (clone $transactions)->with([
                'payment.booking.renter:id,name',
                'payment.booking.space.property',
            ])->latest('received_at')->paginate(20)->withQueryString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $transactions = $this->transactions($request->user()->id, $filters)
            ->with(['payment.booking.renter:id,name', 'payment.booking.space.property'])
            ->orderBy('received_at')
            ->orderBy('id');

        return response()->streamDownload(function () use ($transactions): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Receipt reference', 'Date received', 'Property', 'Rental space', 'Booking reference', 'Renter', 'Obligation', 'Amount (PHP)', 'Payment method']);
            $transactions->chunkById(500, function ($rows) use ($stream): void {
                foreach ($rows as $transaction) {
                    $booking = $transaction->payment->booking;
                    fputcsv($stream, [
                        $transaction->receipt_number,
                        $transaction->received_at->toDateString(),
                        $booking->space->property->property_name,
                        $booking->space->space_name,
                        $booking->reference,
                        $booking->renter->name,
                        (string) str($transaction->payment->payment_type)->replace('_', ' ')->title(),
                        number_format((float) $transaction->amount, 2, '.', ''),
                        (string) str($transaction->payment_method)->replace('_', ' ')->title(),
                    ]);
                }
            });
            fclose($stream);
        }, 'spacematch-owner-collections.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where('owner_id', $request->user()->id),
            ],
            'from' => ['nullable', 'date', 'before_or_equal:today'],
            'to' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : today()->startOfMonth();
        $to = isset($data['to']) ? Carbon::parse($data['to'])->endOfDay() : today()->endOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }

        return [
            'property_id' => isset($data['property_id']) ? (int) $data['property_id'] : null,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function transactions(int $ownerId, array $filters): Builder
    {
        return PaymentTransaction::query()
            ->whereHas('payment.booking.space.property', function (Builder $property) use ($ownerId, $filters): void {
                $property->where('owner_id', $ownerId)
                    ->when($filters['property_id'], fn (Builder $query, int $id) => $query->whereKey($id));
            })
            ->whereBetween('received_at', [$filters['from'], $filters['to']]);
    }

    private function obligations(int $ownerId, array $filters): Builder
    {
        return Payment::query()
            ->whereNotIn('payments.payment_status', ['cancelled'])
            ->whereHas('booking.space.property', function (Builder $property) use ($ownerId, $filters): void {
                $property->where('owner_id', $ownerId)
                    ->when($filters['property_id'], fn (Builder $query, int $id) => $query->whereKey($id));
            });
    }
}
