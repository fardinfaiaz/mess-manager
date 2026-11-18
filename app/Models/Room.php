<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = ['mess_id', 'name', 'capacity'];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function notices()
    {
        return $this->hasMany(Notice::class);
    }

    // People currently living in this room
    public function activeMembers()
    {
        return $this->hasMany(Membership::class)->where('status', 'active');
    }

    // The room leader (at most one per room)
    public function leader()
    {
        return $this->hasOne(Membership::class)
            ->where('status', 'active')
            ->where('role', 'room_leader');
    }

    // Business rules about seats live here, in the Model
    public function occupancy(): int
    {
        return $this->activeMembers()->count();
    }

    public function vacantSeats(): int
    {
        return max(0, $this->capacity - $this->occupancy());
    }

    public function hasFreeSeat(): bool
    {
        return $this->vacantSeats() > 0;
    }
}