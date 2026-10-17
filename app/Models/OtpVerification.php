<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'user_id',
        'verification_type',
        'purpose',
        'destination_hash',
        'destination_encrypted',
        'code_hash',
        'attempts',
        'sent_at',
        'expires_at',
        'consumed_at',
    ];

    protected $hidden = [
        'destination_hash',
        'destination_encrypted',
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'destination_encrypted' => 'encrypted',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}