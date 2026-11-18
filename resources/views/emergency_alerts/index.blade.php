@extends('layouts.app')

@section('title', 'Emergency alerts')

@section('content')
    <h2>Emergency alerts for {{ $mess->name }}</h2>

    <h3>Active now</h3>
    @forelse ($active as $alert)
        <div style="background:#fff3cd;border:1px solid #e0a800;border-left:6px solid #b00020;border-radius:6px;padding:12px;margin-bottom:12px">
            <strong>&#9888; {{ $alert->typeLabel() }}</strong>
            <span style="font-size:13px;color:#555"> &middot; posted {{ $alert->created_at->diffForHumans() }}, active until {{ $alert->expires_at->format('d M, H:i') }}</span>
            <p style="margin:6px 0">{!! nl2br(e($alert->message)) !!}</p>

            @if (in_array($alert->id, $acknowledgedIds))
                <small>&#10003; You have acknowledged this alert.</small>
            @else
                <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm" style="margin-top:0">I have seen this</button>
                </form>
            @endif
        </div>
    @empty
        <p>No active emergency alerts.</p>
    @endforelse

    <h3>Earlier alerts</h3>
    <table>
        <tr><th>Type</th><th>Message</th><th>Outcome</th></tr>
        @forelse ($closed as $alert)
            <tr>
                <td>{{ $alert->typeLabel() }}<br><small>{{ $alert->created_at->format('d M Y') }}</small></td>
                <td>{{ $alert->message }}</td>
                <td>{{ $alert->statusLabel() }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No earlier alerts.</td></tr>
        @endforelse
    </table>

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
