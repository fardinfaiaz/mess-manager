<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskTypeController extends Controller
{
    public function index(Request $request)
    {
        $membership = $this->requireManagerMembership($request);
        $mess = $membership->mess;

        $taskTypes = TaskType::forMess($mess->id)
            ->withCount('assignments')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('tasks.types', compact('mess', 'taskTypes'));
    }

    public function store(Request $request)
    {
        $membership = $this->requireManagerMembership($request);
        $messId = $membership->mess_id;

        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('task_types', 'name')->where('mess_id', $messId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'This mess already has a task type with that name.',
        ]);

        TaskType::create([
            'mess_id'     => $messId,
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => true,
        ]);

        return redirect()->route('task-types.index')
            ->with('success', "Task type \"{$data['name']}\" added.");
    }

    public function update(Request $request, TaskType $taskType)
    {
        $membership = $this->requireManagerMembership($request);
        $this->ensureOwnTaskType($membership, $taskType);

        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('task_types', 'name')
                    ->where('mess_id', $membership->mess_id)
                    ->ignore($taskType->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'name.unique' => 'This mess already has a task type with that name.',
        ]);

        $taskType->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('task-types.index')
            ->with('success', "Task type \"{$taskType->name}\" updated.");
    }

    public function toggle(Request $request, TaskType $taskType)
    {
        $membership = $this->requireManagerMembership($request);
        $this->ensureOwnTaskType($membership, $taskType);

        $taskType->is_active = ! $taskType->is_active;
        $taskType->save();

        $stateLabel = $taskType->is_active ? 'reactivated' : 'deactivated';

        return redirect()->route('task-types.index')
            ->with('success', "Task type \"{$taskType->name}\" {$stateLabel}.");
    }

    private function requireManagerMembership(Request $request): Membership
    {
        $membership = $request->user()?->activeMembership()->with('mess')->first();

        if (! $membership || ! $membership->isManager()) {
            abort(403, 'Only the mess manager can manage task types.');
        }

        return $membership;
    }

    private function ensureOwnTaskType(Membership $membership, TaskType $taskType): void
    {
        if ((int) $taskType->mess_id !== (int) $membership->mess_id) {
            abort(404);
        }
    }
}
