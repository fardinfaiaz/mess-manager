<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mess extends Model
{
    protected $fillable = ['name', 'address', 'area', 'created_by', 'status'];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function activeMembers()
    {
        return $this->hasMany(Membership::class)->where('status', 'active');
    }

    public function joinRequests()
    {
        return $this->hasMany(JoinRequest::class);
    }
    // Total free seats across all rooms (business rule lives in the model)
    public function vacantSeats(): int
    {
        return $this->rooms->sum(fn ($room) => $room->vacantSeats());
    }
}