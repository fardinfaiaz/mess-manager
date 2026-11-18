<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DutyTradeRequest extends Model
{
    public const STATUS_PENDING_ACCEPTANCE = 'pending_acceptance';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING_ACCEPTANCE,
        self::STATUS_PENDING_APPROVAL,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_PENDING_ACCEPTANCE,
        self::STATUS_PENDING_APPROVAL,
    ];

    public const ELIGIBLE_TASK_STATUSES = [
        TaskAssignment::STATUS_PENDING,
        TaskAssignment::STATUS_IN_PROGRESS,
    ];

    // Status and decision metadata are excluded from mass assignment and must
    // only change through the explicit domain methods below.
    protected $fillable = [
        'mess_id',
        'proposer_membership_id',
        'target_membership_id',
        'offered_task_assignment_id',
        'requested_task_assignment_id',
        'reason',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING_ACCEPTANCE,
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'decided_at'  => 'datetime',
    ];

    public function mess()
    {
        return $this->belongsTo(Mess::class);
    }

    public function proposer()
    {
        return $this->belongsTo(Membership::class, 'proposer_membership_id');
    }

    public function target()
    {
        return $this->belongsTo(Membership::class, 'target_membership_id');
    }

    public function offeredAssignment()
    {
        return $this->belongsTo(TaskAssignment::class, 'offered_task_assignment_id');
    }

    public function requestedAssignment()
    {
        return $this->belongsTo(TaskAssignment::class, 'requested_task_assignment_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(Membership::class, 'decided_by_membership_id');
    }

    public function scopeForMess(Builder $query, int $messId): Builder
    {
        return $query->where('mess_id', $messId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeTouchingAssignments(Builder $query, array $assignmentIds): Builder
    {
        return $query->where(function (Builder $q) use ($assignmentIds) {
            $q->whereIn('offered_task_assignment_id', $assignmentIds)
                ->orWhereIn('requested_task_assignment_id', $assignmentIds);
        });
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isPendingAcceptance(): bool
    {
        return $this->status === self::STATUS_PENDING_ACCEPTANCE;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function involvesMembership(Membership $membership): bool
    {
        return (int) $this->proposer_membership_id === (int) $membership->id
            || (int) $this->target_membership_id === (int) $membership->id;
    }

    public function canBeAcceptedBy(Membership $actor): bool
    {
        if ($actor->status !== 'active' || (int) $actor->mess_id !== (int) $this->mess_id) {
            return false;
        }

        if (! $this->isPendingAcceptance()) {
            return false;
        }

        if ((int) $this->target_membership_id !== (int) $actor->id) {
            return false;
        }

        $requested = $this->requestedAssignment;

        return $requested !== null && (int) $requested->membership_id === (int) $actor->id;
    }

    public function canBeCancelledBy(Membership $actor): bool
    {
        return $actor->status === 'active'
            && (int) $actor->mess_id === (int) $this->mess_id
            && $this->isOpen()
            && (int) $this->proposer_membership_id === (int) $actor->id;
    }

    public function canBeReviewedBy(Membership $actor): bool
    {
        if ($actor->status !== 'active' || (int) $actor->mess_id !== (int) $this->mess_id) {
            return false;
        }

        if ($this->involvesMembership($actor)) {
            return false;
        }

        if ($actor->role === 'manager') {
            return true;
        }

        if ($actor->role === 'room_leader') {
            if ($actor->room_id === null) {
                return false;
            }

            $proposer = $this->proposer;
            $target = $this->target;

            return $proposer !== null
                && $target !== null
                && (int) $proposer->mess_id === (int) $actor->mess_id
                && (int) $target->mess_id === (int) $actor->mess_id
                && $proposer->room_id !== null
                && $target->room_id !== null
                && (int) $proposer->room_id === (int) $actor->room_id
                && (int) $target->room_id === (int) $actor->room_id;
        }

        return false;
    }

    public function canBeApprovedBy(Membership $actor): bool
    {
        return $this->isPendingApproval() && $this->canBeReviewedBy($actor);
    }

    public function accept(): bool
    {
        if (! $this->isPendingAcceptance()) {
            return false;
        }

        $this->status = self::STATUS_PENDING_APPROVAL;
        $this->accepted_at = now();

        return $this->save();
    }

    public function approve(?Membership $decider = null, ?string $decisionReason = null): bool
    {
        if (! $this->isPendingApproval()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        if ($decider !== null) {
            $this->decided_by_membership_id = $decider->id;
        }
        $this->decision_reason = $decisionReason;
        $this->decided_at = now();

        return $this->save();
    }

    public function reject(?Membership $decider = null, ?string $decisionReason = null): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $this->status = self::STATUS_REJECTED;
        if ($decider !== null) {
            $this->decided_by_membership_id = $decider->id;
        }
        $this->decision_reason = $decisionReason;
        $this->decided_at = now();

        return $this->save();
    }

    public function cancel(Membership $actor): bool
    {
        if (! $this->canBeCancelledBy($actor)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->decided_by_membership_id = $actor->id;
        $this->decided_at = now();

        return $this->save();
    }

    public function statusLabel(): string
    {
        return ucwords(str_replace('_', ' ', $this->status));
    }

    public static function isTaskEligibleForExchange(TaskAssignment $assignment): bool
    {
        return in_array($assignment->status, self::ELIGIBLE_TASK_STATUSES, true);
    }
}
