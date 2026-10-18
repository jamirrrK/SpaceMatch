<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewingActivity extends Model
{
    protected $fillable = [
        'viewing_appointment_id',
        'user_id',
        'activity_type',
        'scheduled_start_at',
        'reason',
    ];

    protected function casts(): array
    {
        return ['scheduled_start_at' => 'datetime'];
    }

    public function appointment()
    {
        return $this->belongsTo(ViewingAppointment::class, 'viewing_appointment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
