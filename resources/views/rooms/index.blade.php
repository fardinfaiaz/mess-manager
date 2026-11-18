@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
    <h2>Rooms of {{ $mess->name }}</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    <table>
        <tr>
            <th>Room</th><th>Capacity</th><th>Occupied</th><th>Vacant</th>
            <th>Residents</th><th>Leader</th><th></th>
        </tr>
        @forelse ($rooms as $room)
            <tr>
                <td>{{ $room->name }}</td>
                <td>{{ $room->capacity }}</td>
                <td>{{ $room->occupancy() }}</td>
                <td>{{ $room->vacantSeats() }}</td>
                <td>{{ $room->activeMembers->pluck('user.name')->join(', ') ?: '-' }}</td>
                <td>
                    <form method="POST" action="{{ route('rooms.leader', $room) }}">
                        @csrf
                        <select name="membership_id">
                            <option value="">No leader</option>
                            @foreach ($room->activeMembers as $m)
                                @unless ($m->isManager())
                                    <option value="{{ $m->id }}" @selected($m->role === 'room_leader')>{{ $m->user->name }}</option>
                                @endunless
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm">Set</button>
                    </form>
                </td>
                <td><a href="{{ route('rooms.edit', $room) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No rooms yet. Add one below.</td></tr>
        @endforelse
    </table>

    @if ($unassigned->isNotEmpty())
        <p><strong>Members without a room:</strong> {{ $unassigned->pluck('user.name')->join(', ') }}</p>
    @endif

    @if ($rooms->isNotEmpty())
        <h3>Move a member to a room</h3>
        <form method="POST" action="{{ route('rooms.assign') }}">
            @csrf
            <label>Member</label>
            <select name="membership_id" required>
                @foreach ($members as $m)
                    <option value="{{ $m->id }}">{{ $m->user->name }} (now: {{ $m->room?->name ?? 'no room' }})</option>
                @endforeach
            </select>

            <label>Move to room</label>
            <select name="room_id" required>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->vacantSeats() }} free)</option>
                @endforeach
            </select>

            <button type="submit" class="btn">Move member</button>
        </form>
    @endif

    <h3>Add a room</h3>
    <form method="POST" action="{{ route('rooms.store') }}">
        @csrf
        <label for="name">Room name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="e.g. 101" required>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="capacity">Capacity (seats)</label>
        <input type="number" id="capacity" name="capacity" value="{{ old('capacity', 2) }}" min="1" max="20" required>
        @error('capacity') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn">Add room</button>
    </form>

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection