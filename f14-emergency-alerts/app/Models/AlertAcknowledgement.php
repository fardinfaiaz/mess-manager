<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertAcknowledgement extends Model
{
    protected $fillable = ['emergency_alert_id', 'membership_id', 'acknowledged_at'];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function alert()
    {
        return $this->belongsTo(EmergencyAlert::class, 'emergency_alert_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
