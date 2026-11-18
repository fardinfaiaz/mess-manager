<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_MISSED = 'missed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_DONE,
        self::STATUS_VERIFIED,
        self::STATUS_MISSED,
    ];

    // Status, completion, and verification fields are intentionally excluded from
    // mass assignment and must only change via explicit domain methods below.
    protected $fillable = [
        'mess_id',
        'task_type_id',
        'membership_id',
        'assigned_by_membership_id',
        'due_date',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected $casts = [
        'due_date'     => 'date',
        'completed_at' => 'datetime',
        'verified_at'  => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function taskType()
    {
        return $this->belongsTo(TaskType::class);
    }

    public function assignee()
    {
        return $this->belongsTo(Membership::class, 'membership_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(Membership::class, 'assigned_by_membership_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(Membership::class, 'verified_by_membership_id');
    }

    public function scopeForMess(Builder $query, int $messId): Builder
    {
        return $query->where('mess_id', $messId);
    }

    public function isAssignedTo(Membership $membership): bool
    {
        return (int) $this->membership_id === (int) $membership->id;
    }

    public function canBeManagedBy(Membership $actor): bool
    {
        if ($actor->status !== 'active' || (int) $actor->mess_id !== (int) $this->mess_id) {
            return false;
        }

        if ($actor->role === 'manager') {
            return true;
        }

        if ($actor->role === 'room_leader') {
            if ($actor->room_id === null) {
                return false;
            }

            $assignee = $this->assignee;

            return $assignee !== null
                && (int) $assignee->mess_id === (int) $actor->mess_id
                && $assignee->room_id !== null
                && (int) $assignee->room_id === (int) $actor->room_id;
        }

        return false;
    }

    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeMarkedDone(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    public function canBeVerified(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function canBeMarkedMissed(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_IN_PROGRESS], true);
    }

    public function canBeVerifiedBy(Membership $actor): bool
    {
        return ! $this->isAssignedTo($actor)
            && $this->canBeManagedBy($actor)
            && $this->canBeVerified();
    }

    public function canBeMarkedMissedBy(Membership $actor): bool
    {
        return ! $this->isAssignedTo($actor)
            && $this->canBeManagedBy($actor)
            && $this->canBeMarkedMissed();
    }

    public function markInProgress(): bool
    {
        if (! $this->canBeStarted()) {
            return false;
        }

        $this->status = self::STATUS_IN_PROGRESS;

        return $this->save();
    }

    public function markDone(): bool
    {
        if (! $this->canBeMarkedDone()) {
            return false;
        }

        $this->status = self::STATUS_DONE;
        $this->completed_at = now();

        return $this->save();
    }

    public function verify(Membership $verifier): bool
    {
        if (! $this->canBeVerifiedBy($verifier)) {
            return false;
        }

        $this->status = self::STATUS_VERIFIED;
        $this->verified_by_membership_id = $verifier->id;
        $this->verified_at = now();

        return $this->save();
    }

    public function markMissed(): bool
    {
        if (! $this->canBeMarkedMissed()) {
            return false;
        }

        $this->status = self::STATUS_MISSED;

        return $this->save();
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }
}
