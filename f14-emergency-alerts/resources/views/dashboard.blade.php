@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2>Welcome, {{ $user->name }}</h2>

    @if ($membership)
        <p><strong>Mess:</strong> {{ $membership->mess->name }} ({{ $membership->mess->area }})</p>
        <p><strong>Your role:</strong> {{ ucfirst(str_replace('_', ' ', $membership->role)) }}</p>
        <p><strong>Room:</strong> {{ $membership->room?->name ?? 'Not assigned yet' }}</p>

        <a class="btn" href="{{ route('alerts.index') }}">Emergency alerts</a>

        @if ($membership->isManager())
            <a class="btn" href="{{ route('rooms.index') }}">Manage rooms</a>
            <a class="btn" href="{{ route('join.index') }}">Join requests ({{ $pendingCount }} pending)</a>
        @endif
    @else
        <p>You are not part of a mess yet.</p>
        <a class="btn" href="{{ route('messes.search') }}">Find a mess to join</a>
        <a class="btn" href="{{ route('messes.create') }}">Create a new mess</a>

        @if ($requests->isNotEmpty())
            <h3>Your join requests</h3>
            <table>
                <tr><th>Mess</th><th>Status</th><th>Manager's note</th></tr>
                @foreach ($requests as $req)
                    <tr>
                        <td>{{ $req->mess->name }}</td>
                        <td>{{ ucfirst($req->status) }}</td>
                        <td>{{ $req->decision_reason ?? '-' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endif
@endsection