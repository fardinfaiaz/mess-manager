@extends('layouts.app')

@section('title', 'Join requests')

@section('content')
    <h2>Join requests for {{ $mess->name }}</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    @forelse ($pending as $req)
        <div class="card">
            <p><strong>{{ $req->user->name }}</strong> ({{ $req->user->email }}) &middot; sent {{ $req->created_at->diffForHumans() }}</p>
            <p>Preferred room: {{ $req->preferredRoom?->name ?? 'No preference' }}</p>
            @if ($req->message) <p>Message: "{{ $req->message }}"</p> @endif

            @if ($rooms->isEmpty())
                <p class="error">No room has a free seat. Add a room or raise a room's capacity first.</p>
            @else
                <form method="POST" action="{{ route('join.approve', $req) }}">
                    @csrf
                    <label>Place in room</label>
                    <select name="room_id" required>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected($room->id == $req->preferred_room_id)>
                                {{ $room->name }} ({{ $room->vacantSeats() }} free)
                            </option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn">Approve</button>
                </form>
            @endif

            <form method="POST" action="{{ route('join.reject', $req) }}">
                @csrf
                <label>Reason for rejecting</label>
                <input type="text" name="decision_reason" maxlength="255" required>
                <button type="submit" class="btn" style="background:#b00020">Reject</button>
            </form>
        </div>
    @empty
        <p>No pending requests.</p>
    @endforelse

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection