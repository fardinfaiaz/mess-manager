@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2>Welcome, {{ $user->name }}</h2>

    @foreach ($activeAlerts as $alert)
        <div style="background:#fff3cd;border:1px solid #e0a800;border-left:6px solid #b00020;border-radius:6px;padding:12px;margin-bottom:12px">
            <strong>&#9888; {{ $alert->typeLabel() }}</strong>
            <span style="font-size:13px;color:#555"> &middot; until {{ $alert->expires_at->format('d M, H:i') }}</span>
            <p style="margin:6px 0">{!! nl2br(e($alert->message)) !!}</p>
            @unless ($membership->isManager() || in_array($alert->id, $acknowledgedIds))
                <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="margin-top:0">I have seen this</button>
                </form>
            @endunless
        </div>
    @endforeach

    @if ($membership)
        <p><strong>Mess:</strong> {{ $membership->mess->name }} ({{ $membership->mess->area }})</p>
        <p><strong>Your role:</strong> {{ ucfirst(str_replace('_', ' ', $membership->role)) }}</p>
        <p><strong>Room:</strong> {{ $membership->room?->name ?? 'Not assigned yet' }}</p>

        @if ($latestNotices->isNotEmpty())
            <h3>Latest notices</h3>
            @foreach ($latestNotices as $notice)
                @include('notices._card', ['notice' => $notice])
            @endforeach
        @endif

        <a class="btn" href="{{ route('notices.index') }}">Notice board</a>
        <a class="btn" href="{{ route('alerts.index') }}">Emergency alerts</a>

        @if ($membership->isManager())
            <a class="btn" href="{{ route('rooms.index') }}">Manage rooms</a>
            <a class="btn" href="{{ route('join.index') }}">Join requests ({{ $pendingCount }} pending)</a>
        @endif

        {{-- F9 Household task assignment (Raziur) --}}
        <a class="btn" href="{{ route('tasks.index') }}">Household tasks</a>

        {{-- F10 Task tracking and duty exchange (Raziur) --}}
        <a class="btn" href="{{ route('duty-trades.index') }}">Duty exchanges</a>
        {{-- F10 Task tracking and duty exchange (Raziur) --}}
        <a class="btn" href="{{ route('duty-trades.index') }}">Duty exchanges</a>

        {{-- F5 Daily meal on/off and guest meals (Prithu) --}}
        <a class="btn" href="{{ route('meals.index') }}">Daily meals &amp; guests</a>
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