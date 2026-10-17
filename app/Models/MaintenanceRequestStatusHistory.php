<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequestStatusHistory extends Model
{
    protected $table = 'maintenance_request_status_history';

    protected $fillable = [
        'maintenance_request_id',
        'actor_id',
        'from_status',
        'to_status',
        'notes',
    ];

    public function maintenanceRequest()
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
