<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\TaskAssignment;
use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $membership = $this->requireActiveMembership($request, ['mess', 'room']);
        $mess = $membership->mess;

        $statusFilter = (string) $request->query('status', 'all');
        if ($statusFilter !== 'all' && ! in_array($statusFilter, TaskAssignment::STATUSES, true)) {
            $statusFilter = 'all';
        }

        $myTasks = TaskAssignment::forMess($mess->id)
            ->where('membership_id', $membership->id)
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->with(['taskType', 'assignedBy.user', 'verifiedBy.user'])
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $isManager = $membership->role === 'manager';
        $isRoomLeader = $membership->role === 'room_leader';
        $canManage = $isManager || $isRoomLeader;

        $activeTaskTypes = collect();
        $eligibleAssignees = collect();
        $managedTasks = collect();

        if ($canManage) {
            $activeTaskTypes = TaskType::forMess($mess->id)
                ->active()
                ->orderBy('name')
                ->get();

            if ($isManager) {
                $eligibleAssignees = Membership::where('mess_id', $mess->id)
                    ->where('status', 'active')
                    ->where('role', '!=', 'manager')
                    ->with(['user', 'room'])
                    ->get()
                    ->sortBy(fn ($m) => strtolower($m->user->name))
                    ->values();

                $managedTasks = TaskAssignment::forMess($mess->id)
                    ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
                    ->with(['taskType', 'assignee.user', 'assignee.room', 'assignedBy.user', 'verifiedBy.user'])
                    ->orderBy('due_date')
                    ->orderByDesc('id')
                    ->get();
            } elseif ($isRoomLeader && $membership->room_id !== null) {
                $eligibleAssignees = Membership::where('mess_id', $mess->id)
                    ->where('status', 'active')
                    ->where('role', '!=', 'manager')
                    ->where('room_id', $membership->room_id)
                    ->with(['user', 'room'])
                    ->get()
                    ->sortBy(fn ($m) => strtolower($m->user->name))
                    ->values();

                $managedTasks = TaskAssignment::forMess($mess->id)
                    ->whereHas('assignee', function ($q) use ($mess, $membership) {
                        $q->where('mess_id', $mess->id)
                            ->where('room_id', $membership->room_id);
                    })
                    ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
                    ->with(['taskType', 'assignee.user', 'assignee.room', 'assignedBy.user', 'verifiedBy.user'])
                    ->orderBy('due_date')
                    ->orderByDesc('id')
                    ->get();
            }
        }

        return view('tasks.index', [
            'mess'              => $mess,
            'membership'        => $membership,
            'isManager'         => $isManager,
            'isRoomLeader'      => $isRoomLeader,
            'canManage'         => $canManage,
            'statusFilter'      => $statusFilter,
            'statuses'          => TaskAssignment::STATUSES,
            'myTasks'           => $myTasks,
            'managedTasks'      => $managedTasks,
            'activeTaskTypes'   => $activeTaskTypes,
            'eligibleAssignees' => $eligibleAssignees,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $this->requireManagerOrRoomLeader($request);

        if ($actor->role === 'room_leader' && $actor->room_id === null) {
            abort(403, 'You must be assigned to a room to assign tasks as a room leader.');
        }

        $data = $request->validate([
            'task_type_id'  => [
                'required',
                'integer',
                Rule::exists('task_types', 'id')
                    ->where('mess_id', $actor->mess_id)
                    ->where('is_active', 1),
            ],
            'membership_id' => [
                'required',
                'integer',
                Rule::exists('memberships', 'id')
                    ->where('mess_id', $actor->mess_id)
                    ->where('status', 'active')
                    ->where(fn ($query) => $query->where('role', '!=', 'manager')),
            ],
            'due_date'      => ['required', 'date', 'after_or_equal:today'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ], [
            'task_type_id.exists'     => 'Please select an active task type from your mess.',
            'membership_id.exists'    => 'Please select an active non-manager resident from your mess.',
            'due_date.after_or_equal' => 'The due date must be today or a future date.',
        ]);

        $assignment = DB::transaction(function () use ($actor, $data) {
            $assignee = Membership::where('id', $data['membership_id'])
                ->where('mess_id', $actor->mess_id)
                ->where('status', 'active')
                ->where('role', '!=', 'manager')
                ->with('user')
                ->lockForUpdate()
                ->first();

            if (! $assignee) {
                abort(422, 'The selected resident is no longer eligible for task assignment.');
            }

            if ($actor->role === 'room_leader' && ($assignee->room_id === null || (int) $assignee->room_id !== (int) $actor->room_id)) {
                abort(403, 'Room leaders can only assign tasks to active residents in their own room.');
            }

            $taskType = TaskType::where('id', $data['task_type_id'])
                ->where('mess_id', $actor->mess_id)
                ->where('is_active', true)
                ->first();

            if (! $taskType) {
                abort(422, 'The selected task type is not active.');
            }

            $created = TaskAssignment::create([
                'mess_id'                   => $actor->mess_id,
                'task_type_id'              => $taskType->id,
                'membership_id'             => $assignee->id,
                'assigned_by_membership_id' => $actor->id,
                'due_date'                  => $data['due_date'],
                'notes'                     => $data['notes'] ?? null,
            ]);

            $created->setRelation('taskType', $taskType);
            $created->setRelation('assignee', $assignee);

            return $created;
        });

        return redirect()->route('tasks.index')
            ->with('success', "Assigned \"{$assignment->taskType->name}\" to {$assignment->assignee->user->name}.");
    }

    public function updateProgress(Request $request, TaskAssignment $taskAssignment)
    {
        $actor = $this->requireActiveMembership($request);
        $this->ensureOwnMessTask($actor, $taskAssignment);

        if (! $taskAssignment->isAssignedTo($actor)) {
            abort(403, 'You can only update progress on tasks assigned to you.');
        }

        $data = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    TaskAssignment::STATUS_IN_PROGRESS,
                    TaskAssignment::STATUS_DONE,
                ]),
            ],
        ]);

        $updated = DB::transaction(function () use ($actor, $taskAssignment, $data) {
            $locked = TaskAssignment::where('id', $taskAssignment->id)
                ->where('mess_id', $actor->mess_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isAssignedTo($actor)) {
                abort(403, 'You can only update progress on tasks assigned to you.');
            }

            if ($data['status'] === TaskAssignment::STATUS_IN_PROGRESS) {
                return $locked->markInProgress();
            }

            return $locked->markDone();
        });

        if (! $updated) {
            return back()->with('error', 'This task cannot be updated to that status from its current state.');
        }

        $label = $data['status'] === TaskAssignment::STATUS_DONE ? 'done' : 'in progress';

        return redirect()->route('tasks.index')
            ->with('success', "Task marked as {$label}.");
    }

    public function verify(Request $request, TaskAssignment $taskAssignment)
    {
        $actor = $this->requireManagerOrRoomLeader($request);
        $this->ensureOwnMessTask($actor, $taskAssignment);

        if ($taskAssignment->isAssignedTo($actor)) {
            abort(403, 'You cannot verify your own task.');
        }

        $taskAssignment->load('assignee');

        if (! $taskAssignment->canBeManagedBy($actor)) {
            abort(403, 'You are not authorized to verify this task.');
        }

        $verified = DB::transaction(function () use ($actor, $taskAssignment) {
            $locked = TaskAssignment::where('id', $taskAssignment->id)
                ->where('mess_id', $actor->mess_id)
                ->with('assignee')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isAssignedTo($actor)) {
                abort(403, 'You cannot verify your own task.');
            }

            if (! $locked->canBeManagedBy($actor)) {
                abort(403, 'You are not authorized to verify this task.');
            }

            return $locked->verify($actor);
        });

        if (! $verified) {
            return back()->with('error', 'Only completed (done) tasks can be verified.');
        }

        return redirect()->route('tasks.index')
            ->with('success', 'Task verified.');
    }

    public function markMissed(Request $request, TaskAssignment $taskAssignment)
    {
        $actor = $this->requireManagerOrRoomLeader($request);
        $this->ensureOwnMessTask($actor, $taskAssignment);

        if ($taskAssignment->isAssignedTo($actor)) {
            abort(403, 'You cannot mark your own task as missed.');
        }

        $taskAssignment->load('assignee');

        if (! $taskAssignment->canBeManagedBy($actor)) {
            abort(403, 'You are not authorized to update this task.');
        }

        $marked = DB::transaction(function () use ($actor, $taskAssignment) {
            $locked = TaskAssignment::where('id', $taskAssignment->id)
                ->where('mess_id', $actor->mess_id)
                ->with('assignee')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isAssignedTo($actor)) {
                abort(403, 'You cannot mark your own task as missed.');
            }

            if (! $locked->canBeManagedBy($actor)) {
                abort(403, 'You are not authorized to update this task.');
            }

            return $locked->markMissed();
        });

        if (! $marked) {
            return back()->with('error', 'Only pending or in-progress tasks can be marked as missed.');
        }

        return redirect()->route('tasks.index')
            ->with('success', 'Task marked as missed.');
    }

    private function requireActiveMembership(Request $request, array $with = []): Membership
    {
        $query = $request->user()?->activeMembership();

        if ($query && $with !== []) {
            $query->with($with);
        }

        $membership = $query?->first();

        if (! $membership) {
            abort(403, 'You must belong to an active mess to access household tasks.');
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

    private function ensureOwnMessTask(Membership $actor, TaskAssignment $taskAssignment): void
    {
        if ((int) $taskAssignment->mess_id !== (int) $actor->mess_id) {
            abort(404);
        }
    }
}
