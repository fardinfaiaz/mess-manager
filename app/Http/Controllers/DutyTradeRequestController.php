<?php

namespace App\Http\Controllers;

use App\Models\DutyTradeRequest;
use App\Models\Membership;
use App\Models\TaskAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DutyTradeRequestController extends Controller
{
    public function index(Request $request)
    {
        $membership = $this->requireActiveMembership($request, ['mess', 'room']);
        $mess = $membership->mess;

        $isManager = $membership->role === 'manager';
        $isRoomLeader = $membership->role === 'room_leader';
        $canReview = $isManager || $isRoomLeader;

        $openAssignmentIds = DutyTradeRequest::forMess($mess->id)
            ->open()
            ->get(['offered_task_assignment_id', 'requested_task_assignment_id'])
            ->flatMap(fn (DutyTradeRequest $t) => [
                $t->offered_task_assignment_id,
                $t->requested_task_assignment_id,
            ])
            ->unique()
            ->values()
            ->all();

        $myEligibleTasks = collect();
        $otherEligibleTasks = collect();

        if (! $isManager) {
            $myEligibleTasks = TaskAssignment::forMess($mess->id)
                ->where('membership_id', $membership->id)
                ->whereIn('status', DutyTradeRequest::ELIGIBLE_TASK_STATUSES)
                ->when($openAssignmentIds !== [], fn ($q) => $q->whereNotIn('id', $openAssignmentIds))
                ->with('taskType')
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get();

            $otherEligibleTasks = TaskAssignment::forMess($mess->id)
                ->where('membership_id', '!=', $membership->id)
                ->whereIn('status', DutyTradeRequest::ELIGIBLE_TASK_STATUSES)
                ->when($openAssignmentIds !== [], fn ($q) => $q->whereNotIn('id', $openAssignmentIds))
                ->whereHas('assignee', function ($q) use ($mess) {
                    $q->where('mess_id', $mess->id)
                        ->where('status', 'active')
                        ->where('role', '!=', 'manager');
                })
                ->with(['taskType', 'assignee.user', 'assignee.room'])
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get();
        }

        $incomingRequests = DutyTradeRequest::forMess($mess->id)
            ->where('target_membership_id', $membership->id)
            ->where('status', DutyTradeRequest::STATUS_PENDING_ACCEPTANCE)
            ->with([
                'proposer.user',
                'proposer.room',
                'offeredAssignment.taskType',
                'requestedAssignment.taskType',
            ])
            ->latest()
            ->get();

        $outgoingRequests = DutyTradeRequest::forMess($mess->id)
            ->where('proposer_membership_id', $membership->id)
            ->with([
                'target.user',
                'target.room',
                'offeredAssignment.taskType',
                'requestedAssignment.taskType',
                'decidedBy.user',
            ])
            ->latest()
            ->get();

        $approvalQueue = collect();

        if ($isManager) {
            $approvalQueue = DutyTradeRequest::forMess($mess->id)
                ->where('status', DutyTradeRequest::STATUS_PENDING_APPROVAL)
                ->where('proposer_membership_id', '!=', $membership->id)
                ->where('target_membership_id', '!=', $membership->id)
                ->with([
                    'proposer.user',
                    'proposer.room',
                    'target.user',
                    'target.room',
                    'offeredAssignment.taskType',
                    'requestedAssignment.taskType',
                ])
                ->oldest('accepted_at')
                ->get();
        } elseif ($isRoomLeader && $membership->room_id !== null) {
            $roomId = $membership->room_id;

            $approvalQueue = DutyTradeRequest::forMess($mess->id)
                ->where('status', DutyTradeRequest::STATUS_PENDING_APPROVAL)
                ->where('proposer_membership_id', '!=', $membership->id)
                ->where('target_membership_id', '!=', $membership->id)
                ->whereHas('proposer', function ($q) use ($mess, $roomId) {
                    $q->where('mess_id', $mess->id)
                        ->where('status', 'active')
                        ->where('room_id', $roomId);
                })
                ->whereHas('target', function ($q) use ($mess, $roomId) {
                    $q->where('mess_id', $mess->id)
                        ->where('status', 'active')
                        ->where('room_id', $roomId);
                })
                ->with([
                    'proposer.user',
                    'proposer.room',
                    'target.user',
                    'target.room',
                    'offeredAssignment.taskType',
                    'requestedAssignment.taskType',
                ])
                ->oldest('accepted_at')
                ->get();
        }

        return view('duty_trades.index', [
            'mess'               => $mess,
            'membership'         => $membership,
            'isManager'          => $isManager,
            'isRoomLeader'       => $isRoomLeader,
            'canReview'          => $canReview,
            'myEligibleTasks'    => $myEligibleTasks,
            'otherEligibleTasks' => $otherEligibleTasks,
            'incomingRequests'   => $incomingRequests,
            'outgoingRequests'   => $outgoingRequests,
            'approvalQueue'      => $approvalQueue,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->requireActiveMembership($request);

        if ($actor->role === 'manager') {
            abort(403, 'Managers cannot propose resident duty exchanges.');
        }

        $data = $request->validate([
            'offered_task_assignment_id'   => ['required', 'integer'],
            'requested_task_assignment_id' => ['required', 'integer', 'different:offered_task_assignment_id'],
            'reason'                       => ['nullable', 'string', 'max:500'],
        ], [
            'requested_task_assignment_id.different' => 'You must choose a different task to exchange with.',
        ]);

        $offeredLookup = TaskAssignment::find($data['offered_task_assignment_id']);
        $requestedLookup = TaskAssignment::find($data['requested_task_assignment_id']);

        if (($offeredLookup && (int) $offeredLookup->mess_id !== (int) $actor->mess_id)
            || ($requestedLookup && (int) $requestedLookup->mess_id !== (int) $actor->mess_id)) {
            abort(404);
        }

        if (! $offeredLookup) {
            throw ValidationException::withMessages([
                'offered_task_assignment_id' => 'The selected offered task does not exist.',
            ]);
        }

        if (! $requestedLookup) {
            throw ValidationException::withMessages([
                'requested_task_assignment_id' => 'The selected requested task does not exist.',
            ]);
        }

        if (! $offeredLookup->isAssignedTo($actor)) {
            abort(403, 'You can only offer a task assigned to you.');
        }

        DB::transaction(function () use ($actor, $data) {
            $ids = [
                (int) $data['offered_task_assignment_id'],
                (int) $data['requested_task_assignment_id'],
            ];
            sort($ids);

            $locked = TaskAssignment::whereIn('id', $ids)
                ->with('assignee')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $offered = $locked->get((int) $data['offered_task_assignment_id']);
            $requested = $locked->get((int) $data['requested_task_assignment_id']);

            if (! $offered || ! $requested) {
                throw ValidationException::withMessages([
                    'offered_task_assignment_id' => 'One of the selected tasks is no longer available.',
                ]);
            }

            if ((int) $offered->mess_id !== (int) $actor->mess_id
                || (int) $requested->mess_id !== (int) $actor->mess_id) {
                abort(404);
            }

            if (! $offered->isAssignedTo($actor)) {
                abort(403, 'You can only offer a task assigned to you.');
            }

            if ((int) $offered->id === (int) $requested->id) {
                throw ValidationException::withMessages([
                    'requested_task_assignment_id' => 'You cannot exchange a task with itself.',
                ]);
            }

            if ((int) $offered->membership_id === (int) $requested->membership_id) {
                throw ValidationException::withMessages([
                    'requested_task_assignment_id' => 'You cannot exchange a task with yourself.',
                ]);
            }

            $proposer = $offered->assignee;
            $target = $requested->assignee;

            if (! $proposer || $proposer->status !== 'active'
                || (int) $proposer->mess_id !== (int) $actor->mess_id
                || $proposer->role === 'manager') {
                throw ValidationException::withMessages([
                    'offered_task_assignment_id' => 'Your membership is not eligible for duty exchange.',
                ]);
            }

            if (! $target || $target->status !== 'active'
                || (int) $target->mess_id !== (int) $actor->mess_id
                || $target->role === 'manager') {
                throw ValidationException::withMessages([
                    'requested_task_assignment_id' => 'The requested task must belong to an active non-manager resident.',
                ]);
            }

            if (! DutyTradeRequest::isTaskEligibleForExchange($offered)) {
                throw ValidationException::withMessages([
                    'offered_task_assignment_id' => 'Only pending or in-progress tasks can be offered for exchange.',
                ]);
            }

            if (! DutyTradeRequest::isTaskEligibleForExchange($requested)) {
                throw ValidationException::withMessages([
                    'requested_task_assignment_id' => 'Only pending or in-progress tasks can be requested for exchange.',
                ]);
            }

            $hasOpenConflict = DutyTradeRequest::forMess($actor->mess_id)
                ->open()
                ->touchingAssignments([$offered->id, $requested->id])
                ->exists();

            if ($hasOpenConflict) {
                throw ValidationException::withMessages([
                    'offered_task_assignment_id' => 'One of the selected tasks is already involved in an open exchange request.',
                ]);
            }

            DutyTradeRequest::create([
                'mess_id'                      => $actor->mess_id,
                'proposer_membership_id'       => $proposer->id,
                'target_membership_id'         => $target->id,
                'offered_task_assignment_id'   => $offered->id,
                'requested_task_assignment_id' => $requested->id,
                'reason'                       => $data['reason'] ?? null,
            ]);
        });

        return redirect()->route('duty-trades.index')
            ->with('success', 'Duty exchange request sent. Waiting for the other resident to accept.');
    }

    public function accept(Request $request, DutyTradeRequest $dutyTradeRequest)
    {
        $actor = $this->requireActiveMembership($request);
        $this->ensureOwnMessTrade($actor, $dutyTradeRequest);

        $result = DB::transaction(function () use ($actor, $dutyTradeRequest) {
            $lockedTrade = DutyTradeRequest::where('id', $dutyTradeRequest->id)
                ->where('mess_id', $actor->mess_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTrade) {
                abort(404);
            }

            $assignmentIds = [
                (int) $lockedTrade->offered_task_assignment_id,
                (int) $lockedTrade->requested_task_assignment_id,
            ];
            sort($assignmentIds);

            $assignments = TaskAssignment::whereIn('id', $assignmentIds)
                ->where('mess_id', $actor->mess_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $offered = $assignments->get((int) $lockedTrade->offered_task_assignment_id);
            $requested = $assignments->get((int) $lockedTrade->requested_task_assignment_id);

            if ((int) $lockedTrade->target_membership_id !== (int) $actor->id
                || ! $requested
                || (int) $requested->membership_id !== (int) $actor->id) {
                abort(403, 'Only the resident assigned to the requested task can accept this exchange.');
            }

            if (! $lockedTrade->isPendingAcceptance()) {
                return ['ok' => false, 'message' => 'This exchange request is no longer awaiting acceptance.'];
            }

            $memberIds = [
                (int) $lockedTrade->proposer_membership_id,
                (int) $lockedTrade->target_membership_id,
            ];
            sort($memberIds);

            $members = Membership::whereIn('id', $memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $proposer = $members->get((int) $lockedTrade->proposer_membership_id);
            $target = $members->get((int) $lockedTrade->target_membership_id);

            if (! $offered || ! $requested
                || ! DutyTradeRequest::isTaskEligibleForExchange($offered)
                || ! DutyTradeRequest::isTaskEligibleForExchange($requested)
                || (int) $offered->membership_id !== (int) $lockedTrade->proposer_membership_id
                || (int) $requested->membership_id !== (int) $lockedTrade->target_membership_id) {
                return ['ok' => false, 'message' => 'One of the tasks is no longer eligible for this exchange.'];
            }

            if (! $proposer || ! $target
                || $proposer->status !== 'active'
                || $target->status !== 'active'
                || $proposer->role === 'manager'
                || $target->role === 'manager'
                || (int) $proposer->mess_id !== (int) $lockedTrade->mess_id
                || (int) $target->mess_id !== (int) $lockedTrade->mess_id) {
                return ['ok' => false, 'message' => 'Both participants must be active non-manager members of this mess.'];
            }

            $lockedTrade->accept();

            return ['ok' => true, 'message' => 'Exchange accepted. Waiting for manager or room leader approval.'];
        });

        return redirect()->route('duty-trades.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function reject(Request $request, DutyTradeRequest $dutyTradeRequest)
    {
        $actor = $this->requireActiveMembership($request);
        $this->ensureOwnMessTrade($actor, $dutyTradeRequest);

        $data = $request->validate([
            'decision_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($actor, $dutyTradeRequest, $data) {
            $lockedTrade = DutyTradeRequest::where('id', $dutyTradeRequest->id)
                ->where('mess_id', $actor->mess_id)
                ->with(['proposer', 'target', 'requestedAssignment'])
                ->lockForUpdate()
                ->first();

            if (! $lockedTrade) {
                abort(404);
            }

            if ($lockedTrade->isPendingAcceptance()) {
                if (! $lockedTrade->canBeAcceptedBy($actor)) {
                    abort(403, 'Only the requested resident can reject this pending exchange.');
                }
            } elseif ($lockedTrade->isPendingApproval()) {
                if (! $lockedTrade->canBeReviewedBy($actor)) {
                    abort(403, 'You are not authorized to reject this exchange request.');
                }
            } else {
                $isAuthorizedAtAll = (int) $lockedTrade->target_membership_id === (int) $actor->id
                    || $lockedTrade->canBeReviewedBy($actor);

                if (! $isAuthorizedAtAll) {
                    abort(403, 'You are not authorized to reject this exchange request.');
                }

                return ['ok' => false, 'message' => 'This exchange request has already been decided.'];
            }

            $lockedTrade->reject($actor, $data['decision_reason'] ?? null);

            return ['ok' => true, 'message' => 'Exchange request rejected. Task assignments remain unchanged.'];
        });

        return redirect()->route('duty-trades.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function cancel(Request $request, DutyTradeRequest $dutyTradeRequest)
    {
        $actor = $this->requireActiveMembership($request);
        $this->ensureOwnMessTrade($actor, $dutyTradeRequest);

        if ((int) $dutyTradeRequest->proposer_membership_id !== (int) $actor->id) {
            abort(403, 'Only the proposer can cancel this exchange request.');
        }

        $result = DB::transaction(function () use ($actor, $dutyTradeRequest) {
            $lockedTrade = DutyTradeRequest::where('id', $dutyTradeRequest->id)
                ->where('mess_id', $actor->mess_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTrade) {
                abort(404);
            }

            if ((int) $lockedTrade->proposer_membership_id !== (int) $actor->id) {
                abort(403, 'Only the proposer can cancel this exchange request.');
            }

            if (! $lockedTrade->isOpen()) {
                return ['ok' => false, 'message' => 'Only open exchange requests can be cancelled.'];
            }

            $lockedTrade->cancel($actor);

            return ['ok' => true, 'message' => 'Exchange request cancelled.'];
        });

        return redirect()->route('duty-trades.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function approve(Request $request, DutyTradeRequest $dutyTradeRequest)
    {
        $actor = $this->requireManagerOrRoomLeader($request);
        $this->ensureOwnMessTrade($actor, $dutyTradeRequest);

        $data = $request->validate([
            'decision_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($actor, $dutyTradeRequest, $data) {
            // 1-2. Lock trade request and both assignment rows in deterministic ID order.
            $lockedTrade = DutyTradeRequest::where('id', $dutyTradeRequest->id)
                ->where('mess_id', $actor->mess_id)
                ->lockForUpdate()
                ->first();

            if (! $lockedTrade) {
                abort(404);
            }

            $assignmentIds = [
                (int) $lockedTrade->offered_task_assignment_id,
                (int) $lockedTrade->requested_task_assignment_id,
            ];
            sort($assignmentIds);

            $lockedAssignments = TaskAssignment::whereIn('id', $assignmentIds)
                ->where('mess_id', $lockedTrade->mess_id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $offered = $lockedAssignments->get((int) $lockedTrade->offered_task_assignment_id);
            $requested = $lockedAssignments->get((int) $lockedTrade->requested_task_assignment_id);

            $memberIds = array_values(array_unique([
                (int) $actor->id,
                (int) $lockedTrade->proposer_membership_id,
                (int) $lockedTrade->target_membership_id,
            ]));
            sort($memberIds);

            $lockedMembers = Membership::whereIn('id', $memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $freshActor = $lockedMembers->get((int) $actor->id);
            $proposer = $lockedMembers->get((int) $lockedTrade->proposer_membership_id);
            $target = $lockedMembers->get((int) $lockedTrade->target_membership_id);

            // 7. Recheck approving user's current authorization from the database.
            if (! $freshActor
                || $freshActor->status !== 'active'
                || (int) $freshActor->mess_id !== (int) $lockedTrade->mess_id
                || ! in_array($freshActor->role, ['manager', 'room_leader'], true)) {
                abort(403, 'You are not authorized to approve this exchange request.');
            }

            if ($lockedTrade->involvesMembership($freshActor)) {
                abort(403, 'You cannot approve an exchange that you are participating in.');
            }

            if ($freshActor->role === 'room_leader') {
                $sameRoom = $freshActor->room_id !== null
                    && $proposer !== null
                    && $target !== null
                    && $proposer->room_id !== null
                    && $target->room_id !== null
                    && (int) $proposer->room_id === (int) $freshActor->room_id
                    && (int) $target->room_id === (int) $freshActor->room_id;

                if (! $sameRoom) {
                    abort(403, 'Room leaders can only approve exchanges between residents in their own room.');
                }
            }

            // 3. Recheck trade status is pending_approval.
            if (! $lockedTrade->isPendingApproval()) {
                return ['ok' => false, 'message' => 'This exchange request is not awaiting approval.'];
            }

            // 4. Recheck both assignments still exist and have eligible statuses.
            if (! $offered
                || ! $requested
                || (int) $offered->id === (int) $requested->id
                || (int) $offered->mess_id !== (int) $lockedTrade->mess_id
                || (int) $requested->mess_id !== (int) $lockedTrade->mess_id
                || ! DutyTradeRequest::isTaskEligibleForExchange($offered)
                || ! DutyTradeRequest::isTaskEligibleForExchange($requested)) {
                return ['ok' => false, 'message' => 'Both tasks must still be pending or in progress to approve this exchange.'];
            }

            // 5. Recheck current assignees match original expected memberships.
            if ((int) $offered->membership_id !== (int) $lockedTrade->proposer_membership_id
                || (int) $requested->membership_id !== (int) $lockedTrade->target_membership_id) {
                return ['ok' => false, 'message' => 'One of the tasks has already been reassigned. Approval cancelled.'];
            }

            // 6. Recheck both memberships are active, non-manager, distinct, and in the same mess.
            if (! $proposer
                || ! $target
                || (int) $proposer->id === (int) $target->id
                || $proposer->status !== 'active'
                || $target->status !== 'active'
                || $proposer->role === 'manager'
                || $target->role === 'manager'
                || (int) $proposer->mess_id !== (int) $lockedTrade->mess_id
                || (int) $target->mess_id !== (int) $lockedTrade->mess_id) {
                return ['ok' => false, 'message' => 'Both participants must be active non-manager members of this mess.'];
            }

            // 8. Swap the two assignments' membership_id values atomically.
            $offered->membership_id = $target->id;
            $offered->save();

            $requested->membership_id = $proposer->id;
            $requested->save();

            // 9-10. Mark the trade approved and record decision metadata.
            $lockedTrade->approve($freshActor, $data['decision_reason'] ?? null);

            // 11. Automatically reject any other open trade requests touching either swapped assignment.
            DutyTradeRequest::forMess($lockedTrade->mess_id)
                ->open()
                ->where('id', '!=', $lockedTrade->id)
                ->touchingAssignments([$offered->id, $requested->id])
                ->update([
                    'status'                   => DutyTradeRequest::STATUS_REJECTED,
                    'decision_reason'          => 'Automatically rejected because one of the tasks was exchanged in another approved request.',
                    'decided_by_membership_id' => $freshActor->id,
                    'decided_at'               => now(),
                    'updated_at'               => now(),
                ]);

            return ['ok' => true, 'message' => 'Duty exchange approved and both task assignments have been swapped.'];
        });

        return redirect()->route('duty-trades.index')
            ->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    private function requireActiveMembership(Request $request, array $with = []): Membership
    {
        $query = $request->user()?->activeMembership();

        if ($query && $with !== []) {
            $query->with($with);
        }

        $membership = $query?->first();

        if (! $membership) {
            abort(403, 'You must belong to an active mess to access duty exchanges.');
        }

        return $membership;
    }

    private function requireManagerOrRoomLeader(Request $request): Membership
    {
        $membership = $this->requireActiveMembership($request);

        if (! in_array($membership->role, ['manager', 'room_leader'], true)) {
            abort(403, 'Only a mess manager or room leader can perform this action.');
        }

        return $membership;
    }

    private function ensureOwnMessTrade(Membership $actor, DutyTradeRequest $dutyTradeRequest): void
    {
        if ((int) $dutyTradeRequest->mess_id !== (int) $actor->mess_id) {
            abort(404);
        }
    }
}
