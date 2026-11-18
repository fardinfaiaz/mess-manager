@extends('layouts.app')

@section('title', 'Duty exchanges')

@section('content')
    <style>
        .f10-meta { color: #555; font-size: 14px; margin: 4px 0; }
        .f10-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .f10-actions form { margin: 0; }
        .f10-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .f10-badge-pending_acceptance { background: #f4f6f8; color: #222; border: 1px solid #bbb; }
        .f10-badge-pending_approval { background: #e8eef5; color: #1f3b57; }
        .f10-badge-approved { background: #e5f4e4; color: #1b5e20; }
        .f10-badge-rejected { background: #fbebeb; color: #b00020; }
        .f10-badge-cancelled { background: #f4f6f8; color: #555; }
    </style>

    <h2>Duty exchanges &mdash; {{ $mess->name }}</h2>

    @if ($isRoomLeader)
        <p class="f10-meta">
            Room leader approval scope:
            <strong>{{ $membership->room ? 'Room ' . $membership->room->name : 'Not assigned to a room' }}</strong>
        </p>
    @endif

    @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
    @endif

    {{-- Section 1: My assigned tasks available for exchange --}}
    @if (! $isManager)
        <h3>1. My assigned tasks available for exchange</h3>

        @if ($myEligibleTasks->isEmpty())
            <p class="f10-meta">You currently have no pending or in-progress tasks available to exchange.</p>
        @elseif ($otherEligibleTasks->isEmpty())
            <div class="card">
                <p>Your available task(s):</p>
                <ul>
                    @foreach ($myEligibleTasks as $task)
                        <li>{{ $task->taskType->name }} &mdash; due {{ $task->due_date->format('Y-m-d') }} ({{ $task->statusLabel() }})</li>
                    @endforeach
                </ul>
                <p class="f10-meta">No other residents currently have an eligible open task to exchange with.</p>
            </div>
        @else
            <form method="POST" action="{{ route('duty-trades.store') }}">
                @csrf

                <label for="offered_task_assignment_id">My task to offer</label>
                <select id="offered_task_assignment_id" name="offered_task_assignment_id" required>
                    <option value="">Select your task</option>
                    @foreach ($myEligibleTasks as $task)
                        <option value="{{ $task->id }}" @selected((string) old('offered_task_assignment_id') === (string) $task->id)>
                            {{ $task->taskType->name }} (Due: {{ $task->due_date->format('Y-m-d') }}, {{ $task->statusLabel() }})
                        </option>
                    @endforeach
                </select>
                @error('offered_task_assignment_id') <div class="error">{{ $message }}</div> @enderror

                <label for="requested_task_assignment_id">Task to exchange with</label>
                <select id="requested_task_assignment_id" name="requested_task_assignment_id" required>
                    <option value="">Select another resident's task</option>
                    @foreach ($otherEligibleTasks as $task)
                        <option value="{{ $task->id }}" @selected((string) old('requested_task_assignment_id') === (string) $task->id)>
                            {{ $task->taskType->name }} &mdash; {{ $task->assignee->user->name }}
                            ({{ $task->assignee->room ? 'Room ' . $task->assignee->room->name : 'no room' }},
                            Due: {{ $task->due_date->format('Y-m-d') }})
                        </option>
                    @endforeach
                </select>
                @error('requested_task_assignment_id') <div class="error">{{ $message }}</div> @enderror

                <label for="reason">Reason (optional)</label>
                <textarea id="reason" name="reason" rows="2" maxlength="500" placeholder="e.g. Exam schedule conflict">{{ old('reason') }}</textarea>
                @error('reason') <div class="error">{{ $message }}</div> @enderror

                <button type="submit" class="btn">Request duty exchange</button>
            </form>
        @endif

        {{-- Section 2: Incoming requests awaiting my acceptance --}}
        <h3>2. Incoming requests awaiting my acceptance</h3>

        @forelse ($incomingRequests as $trade)
            <div class="card">
                <p>
                    <strong>From {{ $trade->proposer?->user?->name ?? '-' }}</strong>
                    @if ($trade->proposer?->room)
                        (Room {{ $trade->proposer->room->name }})
                    @endif
                    &middot;
                    <span class="f10-badge f10-badge-{{ $trade->status }}">{{ $trade->statusLabel() }}</span>
                </p>
                <p class="f10-meta">
                    <strong>You receive:</strong> {{ $trade->offeredAssignment->taskType->name }}
                    (Due {{ $trade->offeredAssignment->due_date->format('Y-m-d') }})
                </p>
                <p class="f10-meta">
                    <strong>You give up:</strong> {{ $trade->requestedAssignment->taskType->name }}
                    (Due {{ $trade->requestedAssignment->due_date->format('Y-m-d') }})
                </p>
                @if ($trade->reason)
                    <p>Reason: "{{ $trade->reason }}"</p>
                @endif

                <div class="f10-actions">
                    <form
                        method="POST"
                        action="{{ route('duty-trades.accept', $trade) }}"
                        onsubmit="return confirm('Accept this exchange and send it for manager/room leader approval?');"
                    >
                        @csrf
                        <button type="submit" class="btn btn-sm">Accept exchange</button>
                    </form>
                </div>

                <form
                    method="POST"
                    action="{{ route('duty-trades.reject', $trade) }}"
                    onsubmit="return confirm('Reject this duty exchange request?');"
                >
                    @csrf
                    <label for="reject_incoming_{{ $trade->id }}">Rejection note (optional)</label>
                    <input type="text" id="reject_incoming_{{ $trade->id }}" name="decision_reason" maxlength="255">
                    <button type="submit" class="btn btn-sm" style="background:#b00020">Reject</button>
                </form>
            </div>
        @empty
            <p class="f10-meta">No incoming exchange requests awaiting your acceptance.</p>
        @endforelse

        {{-- Section 3: My outgoing requests --}}
        <h3>3. My outgoing requests</h3>

        @forelse ($outgoingRequests as $trade)
            <div class="card">
                <p>
                    <strong>To {{ $trade->target?->user?->name ?? '-' }}</strong>
                    @if ($trade->target?->room)
                        (Room {{ $trade->target->room->name }})
                    @endif
                    &middot;
                    <span class="f10-badge f10-badge-{{ $trade->status }}">{{ $trade->statusLabel() }}</span>
                </p>
                <p class="f10-meta">
                    <strong>Offered:</strong> {{ $trade->offeredAssignment->taskType->name }}
                    (Due {{ $trade->offeredAssignment->due_date->format('Y-m-d') }})
                    &middot;
                    <strong>Requested:</strong> {{ $trade->requestedAssignment->taskType->name }}
                    (Due {{ $trade->requestedAssignment->due_date->format('Y-m-d') }})
                </p>
                @if ($trade->reason)
                    <p class="f10-meta">Reason: "{{ $trade->reason }}"</p>
                @endif
                @if ($trade->decision_reason)
                    <p class="f10-meta">Decision note: {{ $trade->decision_reason }}</p>
                @endif
                @if ($trade->decided_at)
                    <p class="f10-meta">
                        Decided by {{ $trade->decidedBy?->user?->name ?? '-' }}
                        on {{ $trade->decided_at->format('Y-m-d H:i') }}
                    </p>
                @endif

                @if ($trade->canBeCancelledBy($membership))
                    <div class="f10-actions">
                        <form
                            method="POST"
                            action="{{ route('duty-trades.cancel', $trade) }}"
                            onsubmit="return confirm('Cancel this duty exchange request?');"
                        >
                            @csrf
                            <button type="submit" class="btn btn-sm" style="background:#b00020">Cancel request</button>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <p class="f10-meta">You have not proposed any duty exchange requests yet.</p>
        @endforelse
    @endif

    {{-- Section 4: Requests awaiting manager/room leader approval --}}
    @if ($canReview)
        <h3>{{ $isManager ? 'Requests awaiting approval' : '4. Requests awaiting approval (Room ' . ($membership->room?->name ?? '-') . ')' }}</h3>

        @forelse ($approvalQueue as $trade)
            <div class="card">
                <p>
                    <strong>{{ $trade->proposer?->user?->name ?? '-' }}</strong>
                    ({{ $trade->proposer?->room ? 'Room ' . $trade->proposer->room->name : 'no room' }})
                    &harr;
                    <strong>{{ $trade->target?->user?->name ?? '-' }}</strong>
                    ({{ $trade->target?->room ? 'Room ' . $trade->target->room->name : 'no room' }})
                    &middot;
                    <span class="f10-badge f10-badge-{{ $trade->status }}">{{ $trade->statusLabel() }}</span>
                </p>
                <p class="f10-meta">
                    <strong>{{ $trade->proposer?->user?->name ?? 'Proposer' }}'s task:</strong>
                    {{ $trade->offeredAssignment->taskType->name }}
                    (Due {{ $trade->offeredAssignment->due_date->format('Y-m-d') }}, {{ $trade->offeredAssignment->statusLabel() }})
                </p>
                <p class="f10-meta">
                    <strong>{{ $trade->target?->user?->name ?? 'Target' }}'s task:</strong>
                    {{ $trade->requestedAssignment->taskType->name }}
                    (Due {{ $trade->requestedAssignment->due_date->format('Y-m-d') }}, {{ $trade->requestedAssignment->statusLabel() }})
                </p>
                @if ($trade->reason)
                    <p class="f10-meta">Reason: "{{ $trade->reason }}"</p>
                @endif
                @if ($trade->accepted_at)
                    <p class="f10-meta">Accepted by {{ $trade->target?->user?->name ?? '-' }} on {{ $trade->accepted_at->format('Y-m-d H:i') }}</p>
                @endif

                <div class="f10-actions">
                    <form
                        method="POST"
                        action="{{ route('duty-trades.approve', $trade) }}"
                        onsubmit="return confirm('Approve this exchange? Both task assignments will be swapped immediately.');"
                    >
                        @csrf
                        <button type="submit" class="btn btn-sm">Approve &amp; swap</button>
                    </form>
                </div>

                <form
                    method="POST"
                    action="{{ route('duty-trades.reject', $trade) }}"
                    onsubmit="return confirm('Reject this exchange request? Both assignments will remain unchanged.');"
                >
                    @csrf
                    <label for="reject_review_{{ $trade->id }}">Rejection note (optional)</label>
                    <input type="text" id="reject_review_{{ $trade->id }}" name="decision_reason" maxlength="255">
                    <button type="submit" class="btn btn-sm" style="background:#b00020">Reject</button>
                </form>
            </div>
        @empty
            <p class="f10-meta">No accepted exchange requests are currently awaiting approval.</p>
        @endforelse
    @endif

    <p>
        <a href="{{ route('tasks.index') }}">Household tasks</a>
        &middot;
        <a href="{{ route('dashboard') }}">Back to dashboard</a>
    </p>
@endsection
