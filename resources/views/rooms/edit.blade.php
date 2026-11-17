@extends('layouts.app')

@section('title', 'Edit room')

@section('content')
    <h2>Edit room {{ $room->name }}</h2>
    <p>Currently occupied: {{ $room->occupancy() }} of {{ $room->capacity }}</p>

    <form method="POST" action="{{ route('rooms.update', $room) }}">
        @csrf
        @method('PUT')
        <label for="name">Room name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $room->name) }}" required>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="capacity">Capacity (seats)</label>
        <input type="number" id="capacity" name="capacity" value="{{ old('capacity', $room->capacity) }}" required>
        @error('capacity') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn">Save</button>
    </form>

    <p><a href="{{ route('rooms.index') }}">Back to rooms</a></p>
@endsection