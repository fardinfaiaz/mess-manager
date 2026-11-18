<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    // Notice categories (shown in the form and used for validation)
    public const CATEGORIES = [
        'general'     => 'General',
        'meeting'     => 'Meeting',
        'payment'     => 'Payment deadline',
        'rule'        => 'House rule',
        'maintenance' => 'Planned maintenance',
    ];

    protected $fillable = [
        'mess_id', 'room_id', 'created_by', 'title', 'body',
        'category', 'scope', 'pinned', 'expires_at', 'is_automatic',
    ];

    protected $casts = [
        'pinned'       => 'boolean',
        'is_automatic' => 'boolean',
        'expires_at'   => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Notices with no expiry date, or whose expiry date has not passed yet
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    // Notices whose expiry date has passed
    public function scopePrevious(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    // Notices a member is allowed to read: mess-wide ones, plus those for their own room.
    // The manager sees every notice of the mess.
    public function scopeVisibleTo(Builder $query, Membership $membership): Builder
    {
        $query->where('mess_id', $membership->mess_id);

        if ($membership->isManager()) {
            return $query;
        }

        return $query->where(function ($q) use ($membership) {
            $q->whereNull('room_id');
            if ($membership->room_id) {
                $q->orWhere('room_id', $membership->room_id);
            }
        });
    }

    public function isCurrent(): bool
    {
        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function isVisibleTo(Membership $membership): bool
    {
        if ($this->mess_id != $membership->mess_id) {
            return false;
        }

        return $membership->isManager()
            || $this->room_id === null
            || $this->room_id == $membership->room_id;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    // "Whole mess" or the room name, shown next to each notice
    public function audienceLabel(): string
    {
        return $this->room_id ? 'Room: ' . ($this->room?->name ?? '-') : 'Whole mess';
    }
}
