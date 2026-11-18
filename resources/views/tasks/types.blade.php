@extends('layouts.app')

@section('title', 'Task types')

@section('content')
    <style>
        .f9-meta { color: #555; font-size: 14px; margin: 4px 0; }
        .f9-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .f9-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .f9-badge-active { background: #e5f4e4; color: #1b5e20; }
        .f9-badge-inactive { background: #fbebeb; color: #b00020; }
    </style>

    <h2>Task types for {{ $mess->name }}</h2>
    <p class="f9-meta">Define household duties (such as cleaning, kitchen maintenance, and waste disposal) that can be assigned to residents.</p>

    @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
    @endif

    <h3>Add a task type</h3>
    <form method="POST" action="{{ route('task-types.store') }}">
        @csrf

        <label for="new_name">Task type name</label>
        <input
            type="text"
            id="new_name"
            name="name"
            value="{{ old('name') }}"
            placeholder="e.g. Cleaning, Kitchen maintenance, Waste disposal"
            maxlength="100"
            required
        >
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="new_description">Description (optional)</label>
        <textarea id="new_description" name="description" rows="2" maxlength="500">{{ old('description') }}</textarea>
        @error('description') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn">Add task type</button>
    </form>

    <h3>Existing task types</h3>

    @forelse ($taskTypes as $type)
        <div class="card">
            <p>
                <strong>{{ $type->name }}</strong>
                &middot;
                @if ($type->isActive())
                    <span class="f9-badge f9-badge-active">Active</span>
                @else
                    <span class="f9-badge f9-badge-inactive">Inactive</span>
                @endif
                &middot;
                <span class="f9-meta">{{ $type->assignments_count }} assignment(s)</span>
            </p>

            <form method="POST" action="{{ route('task-types.update', $type) }}">
                @csrf
                @method('PUT')

                <label for="name_{{ $type->id }}">Name</label>
                <input
                    type="text"
                    id="name_{{ $type->id }}"
                    name="name"
                    value="{{ $type->name }}"
                    maxlength="100"
                    required
                >

                <label for="description_{{ $type->id }}">Description</label>
                <textarea id="description_{{ $type->id }}" name="description" rows="2" maxlength="500">{{ $type->description }}</textarea>

                <button type="submit" class="btn btn-sm">Save</button>
            </form>

            <div class="f9-actions">
                <form method="POST" action="{{ route('task-types.toggle', $type) }}">
                    @csrf
                    @if ($type->isActive())
                        <button type="submit" class="btn btn-sm" style="background:#b00020">Deactivate</button>
                    @else
                        <button type="submit" class="btn btn-sm">Reactivate</button>
                    @endif
                </form>
            </div>
        </div>
    @empty
        <p>No task types added yet.</p>
    @endforelse

    <p>
        <a href="{{ route('tasks.index') }}">Back to household tasks</a>
        &middot;
        <a href="{{ route('dashboard') }}">Back to dashboard</a>
    </p>
@endsection
