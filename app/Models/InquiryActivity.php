<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InquiryActivity extends Model
{
    protected $fillable = [
        'inquiry_id',
        'user_id',
        'activity_type',
        'body',
        'from_status',
        'to_status',
        'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
