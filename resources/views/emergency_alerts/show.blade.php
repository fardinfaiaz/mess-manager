@extends('layouts.app')

@section('title', 'Alert details')

@section('content')
    <h2>{{ $alert->typeLabel() }}</h2>

    <div class="card">
        <p>{!! nl2br(e($alert->message)) !!}</p>
        <p>
            <strong>Status:</strong> {{ $alert->statusLabel() }}<br>
            <strong>Published:</strong> {{ $alert->created_at->format('d M Y, H:i') }}
            by {{ $alert->creator->name }}<br>
            <strong>Expires:</strong> {{ $alert->expires_at->format('d M Y, H:i') }}
            @if ($alert->resolved_at)
                <br><strong>Resolved:</strong> {{ $alert->resolved_at->format('d M Y, H:i') }}
            @endif
        </p>

        @if ($alert->isActive())
            <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                @csrf
                <button type="submit" class="btn btn-sm">Mark as resolved</button>
            </form>
        @endif
    </div>

    <h3>Acknowledged ({{ $acknowledged->count() }})</h3>
    <table>
        <tr><th>Resident</th><th>When</th></tr>
        @forelse ($acknowledged as $ack)
            <tr>
                <td>{{ $ack->membership->user->name }}</td>
                <td>{{ $ack->acknowledged_at->format('d M, H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="2">Nobody has acknowledged this alert yet.</td></tr>
        @endforelse
    </table>

    <h3>Not acknowledged yet ({{ $pending->count() }})</h3>
    <p>{{ $pending->pluck('user.name')->join(', ') ?: 'Everyone has seen this alert.' }}</p>

    <p><a href="{{ route('alerts.index') }}">Back to alerts</a></p>
@endsection
