<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $table = 'maintenance_requests';

    protected $fillable = [
        'evidence_path',
        'booking_id',
        'description',
        'status',
        'owner_response',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(MaintenanceRequestStatusHistory::class)->oldest();
    }
}
