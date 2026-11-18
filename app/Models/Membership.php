<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $fillable = ['user_id', 'mess_id', 'room_id', 'role', 'status', 'joined_at', 'left_at'];

    protected $casts = [
        'joined_at' => 'date',
        'left_at'   => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function alertAcknowledgements()
    {
        return $this->hasMany(AlertAcknowledgement::class);
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }
}