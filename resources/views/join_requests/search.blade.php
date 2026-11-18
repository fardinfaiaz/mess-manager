@extends('layouts.app')

@section('title', 'Find a mess')

@section('content')
    <h2>Find a mess</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    <form method="GET" action="{{ route('messes.search') }}">
        <label for="q">Name or area</label>
        <input type="text" id="q" name="q" value="{{ $q }}" placeholder="e.g. Mirpur">
        <button type="submit" class="btn">Search</button>
    </form>

    @forelse ($messes as $mess)
        <div class="card">
            <h3>{{ $mess->name }}</h3>
            <p>{{ $mess->address }}, {{ $mess->area }} &middot; <strong>{{ $mess->vacantSeats() }}</strong> vacant seat(s)</p>

            <form method="POST" action="{{ route('join.store', $mess) }}">
                @csrf
                <label>Preferred room (optional)</label>
                <select name="preferred_room_id">
                    <option value="">No preference</option>
                    @foreach ($mess->rooms as $room)
                        @if ($room->hasFreeSeat())
                            <option value="{{ $room->id }}">{{ $room->name }} ({{ $room->vacantSeats() }} free)</option>
                        @endif
                    @endforeach
                </select>

                <label>Message to manager (optional)</label>
                <textarea name="message" rows="2" maxlength="500"></textarea>

                <button type="submit" class="btn">Send join request</button>
            </form>
        </div>
    @empty
        <p>No messes with vacant seats match your search.</p>
    @endforelse

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection