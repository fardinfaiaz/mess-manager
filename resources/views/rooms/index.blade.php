@extends('layouts.app')

@section('title', 'Rooms')

@section('content')
    <h2>Rooms of {{ $mess->name }}</h2>

    <table>
        <tr>
            <th>Room</th><th>Capacity</th><th>Occupied</th><th>Vacant</th>
            <th>Leader</th><th>Residents</th><th></th>
        </tr>
        @forelse ($rooms as $room)
            <tr>
                <td>{{ $room->name }}</td>
                <td>{{ $room->capacity }}</td>
                <td>{{ $room->occupancy() }}</td>
                <td>{{ $room->vacantSeats() }}</td>
                <td>{{ $room->leader?->user->name ?? '-' }}</td>
                <td>{{ $room->activeMembers->pluck('user.name')->join(', ') ?: '-' }}</td>
                <td><a href="{{ route('rooms.edit', $room) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="7">No rooms yet. Add one below.</td></tr>
        @endforelse
    </table>

    @if ($unassigned->isNotEmpty())
        <p><strong>Members without a room:</strong> {{ $unassigned->pluck('user.name')->join(', ') }}</p>
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