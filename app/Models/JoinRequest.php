<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JoinRequest extends Model
{
    protected $fillable = [
        'user_id', 'mess_id', 'preferred_room_id', 'message',
        'status', 'decision_reason', 'decided_by', 'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function preferredRoom()
    {
        return $this->belongsTo(Room::class, 'preferred_room_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}