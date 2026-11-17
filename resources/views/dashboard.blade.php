@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2>Welcome, {{ $user->name }}</h2>

    @if ($membership)
        <p><strong>Mess:</strong> {{ $membership->mess->name }} ({{ $membership->mess->area }})</p>
        <p><strong>Your role:</strong> {{ ucfirst(str_replace('_', ' ', $membership->role)) }}</p>
        <p><strong>Room:</strong> {{ $membership->room?->name ?? 'Not assigned yet' }}</p>
        @if ($membership->isManager())
            <a class="btn" href="{{ route('rooms.index') }}">Manage rooms</a>
        @endif
    @else
        <p>You are not part of a mess yet.</p>
        <a class="btn" href="{{ route('messes.create') }}">Create a mess</a>
    @endif
@endsection