@extends('layouts.app')

@section('title', 'Household tasks')

@section('content')
    <style>
        .f9-date-input { width: 100%; padding: 9px; margin-top: 4px; box-sizing: border-box; border: 1px solid #bbb; border-radius: 4px; font-family: inherit; }
        .f9-meta { color: #555; font-size: 14px; margin: 4px 0; }
        .f9-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .f9-actions form { margin: 0; }
        .f9-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-top: 12px; }
        .f9-filter-field { flex: 1 1 180px; }
        .f9-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .f9-badge-pending { background: #f4f6f8; color: #222; border: 1px solid #bbb; }
        .f9-badge-in_progress { background: #e8eef5; color: #1f3b57; }
        .f9-badge-done { background: #e5f4e4; color: #1b5e20; }
        .f9-badge-verified { background: #1f3b57; color: #fff; }
        .f9-badge-missed { background: #fbebeb; color: #b00020; }
        .f9-table-wrap { overflow-x: auto; }
    </style>

    <h2>Household tasks &mdash; {{ $mess->name }}</h2>

    @if ($isRoomLeader)
        <p class="f9-meta">
            Room leader scope:
            <strong>{{ $membership->room ? 'Room ' . $membership->room->name : 'Not assigned to a room' }}</strong>
        </p>
    @endif

    @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
    @endif

    @if ($isManager)
        <a class="btn" href="{{ route('task-types.index') }}">Manage task types</a>
    @endif

    <form method="GET" action="{{ route('tasks.index') }}" class="f9-filter">
        <div class="f9-filter-field">
            <label for="status">Filter by status</label>
            <select id="status" name="status">
                <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st }}" @selected($statusFilter === $st)>
                        {{ ucwords(str_replace('_', ' ', $st)) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-sm">Filter</button>
        </div>
    </form>

    @if (! $isManager || $myTasks->isNotEmpty())
        <h3>My assigned tasks</h3>

        @forelse ($myTasks as $task)
            <div class="card">
                <p>
                    <strong>{{ $task->taskType->name }}</strong>
                    &middot;
                    <span class="f9-badge f9-badge-{{ $task->status }}">{{ $task->statusLabel() }}</span>
                </p>
                <p class="f9-meta">
                    Due: <strong>{{ $task->due_date->format('Y-m-d') }}</strong>
                    &middot; Assigned by: {{ $task->assignedBy?->user?->name ?? '-' }}
                </p>
                @if ($task->taskType->description)
                    <p class="f9-meta">{{ $task->taskType->description }}</p>
                @endif
                @if ($task->notes)
                    <p>Notes: {{ $task->notes }}</p>
                @endif
                @if ($task->completed_at)
                    <p class="f9-meta">Completed: {{ $task->completed_at->format('Y-m-d H:i') }}</p>
                @endif
                @if ($task->verified_at)
                    <p class="f9-meta">
                        Verified by {{ $task->verifiedBy?->user?->name ?? '-' }}
                        on {{ $task->verified_at->format('Y-m-d H:i') }}
                    </p>
                @endif

                @if ($task->canBeStarted() || $task->canBeMarkedDone())
                    <div class="f9-actions">
                        @if ($task->canBeStarted())
                            <form method="POST" action="{{ route('tasks.progress', $task) }}">
                                @csrf
                                <input type="hidden" name="status" value="in_progress">
                                <button type="submit" class="btn btn-sm">Start (In Progress)</button>
                            </form>
                        @endif

                        @if ($task->canBeMarkedDone())
                            <form method="POST" action="{{ route('tasks.progress', $task) }}">
                                @csrf
                                <input type="hidden" name="status" value="done">
                                <button type="submit" class="btn btn-sm">Mark Done</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p>You have no assigned tasks matching this filter.</p>
        @endforelse
    @endif

    @if ($canManage)
        <h3>Assign a household task</h3>

        @if ($activeTaskTypes->isEmpty())
            <div class="card">
                @if ($isManager)
                    <p>No active task types yet. Add at least one task type (e.g., Cleaning, Kitchen maintenance, Waste disposal) first.</p>
                    <a class="btn btn-sm" href="{{ route('task-types.index') }}">Add task types</a>
                @else
                    <p>No active task types have been created by the mess manager yet.</p>
                @endif
            </div>
        @elseif ($eligibleAssignees->isEmpty())
            <div class="card">
                <p>
                    @if ($isRoomLeader)
                        There are no active residents in your room available for task assignment.
                    @else
                        There are no active non-manager residents in this mess yet.
                    @endif
                </p>
            </div>
        @else
            <form method="POST" action="{{ route('tasks.store') }}">
                @csrf

                <label for="task_type_id">Task type</label>
                <select id="task_type_id" name="task_type_id" required>
                    <option value="">Select a task type</option>
                    @foreach ($activeTaskTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) old('task_type_id') === (string) $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
                @error('task_type_id') <div class="error">{{ $message }}</div> @enderror

                <label for="membership_id">Assign to resident</label>
                <select id="membership_id" name="membership_id" required>
                    <option value="">Select a resident</option>
                    @foreach ($eligibleAssignees as $member)
                        <option value="{{ $member->id }}" @selected((string) old('membership_id') === (string) $member->id)>
                            {{ $member->user->name }} ({{ $member->room ? 'Room ' . $member->room->name : 'no room' }})
                        </option>
                    @endforeach
                </select>
                @error('membership_id') <div class="error">{{ $message }}</div> @enderror

                <label for="due_date">Due date</label>
                <input
                    type="date"
                    id="due_date"
                    name="due_date"
                    class="f9-date-input"
                    value="{{ old('due_date', now()->toDateString()) }}"
                    min="{{ now()->toDateString() }}"
                    required
                >
                @error('due_date') <div class="error">{{ $message }}</div> @enderror

                <label for="notes">Notes (optional)</label>
                <textarea id="notes" name="notes" rows="2" maxlength="500">{{ old('notes') }}</textarea>
                @error('notes') <div class="error">{{ $message }}</div> @enderror

                <button type="submit" class="btn">Assign task</button>
            </form>
        @endif

        <h3>{{ $isManager ? 'All mess tasks' : 'Room ' . ($membership->room?->name ?? '') . ' tasks' }}</h3>

        @if ($managedTasks->isEmpty())
            <p>No tasks found for this filter.</p>
        @else
            <div class="f9-table-wrap">
                <table>
                    <tr>
                        <th>Task</th>
                        <th>Resident</th>
                        <th>Due date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    @foreach ($managedTasks as $task)
                        <tr>
                            <td>
                                <strong>{{ $task->taskType->name }}</strong>
                                @if ($task->notes)
                                    <div class="f9-meta">{{ $task->notes }}</div>
                                @endif
                            </td>
                            <td>
                                {{ $task->assignee?->user?->name ?? '-' }}
                                <div class="f9-meta">
                                    {{ $task->assignee?->room ? 'Room ' . $task->assignee->room->name : 'No room' }}
                                </div>
                            </td>
                            <td>{{ $task->due_date->format('Y-m-d') }}</td>
                            <td>
                                <span class="f9-badge f9-badge-{{ $task->status }}">{{ $task->statusLabel() }}</span>
                                @if ($task->verified_at)
                                    <div class="f9-meta">By {{ $task->verifiedBy?->user?->name ?? '-' }}</div>
                                @endif
                            </td>
                            <td>
                                <div class="f9-actions">
                                    @if ($task->canBeVerifiedBy($membership))
                                        <form method="POST" action="{{ route('tasks.verify', $task) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm">Verify</button>
                                        </form>
                                    @endif

                                    @if ($task->canBeMarkedMissedBy($membership))
                                        <form method="POST" action="{{ route('tasks.missed', $task) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm" style="background:#b00020">Mark Missed</button>
                                        </form>
                                    @endif

                                    @if (! $task->canBeVerifiedBy($membership) && ! $task->canBeMarkedMissedBy($membership))
                                        <span class="f9-meta">-</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
