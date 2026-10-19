<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'amount',
        'payment_date',
        'payment_method',
        'payment_status',
        'due_date',
        'reference',
        'confirmed_by',
        'payment_type',
        'paid_amount',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function submissions()
    {
        return $this->hasMany(PaymentSubmission::class);
    }

    public function getOutstandingBalanceAttribute(): float
    {
        if ($this->payment_status === 'cancelled') {
            return 0.0;
        }

        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->payment_status === 'cancelled') {
            return 'cancelled';
        }
        if ($this->outstanding_balance <= 0) {
            return 'paid';
        }
        if ($this->due_date?->lt(today())) {
            return 'overdue';
        }

        return (float) $this->paid_amount > 0 ? 'partially_paid' : 'pending';
    }
}
