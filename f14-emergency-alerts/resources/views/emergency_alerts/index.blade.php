@extends('layouts.app')

@section('title', 'Emergency alerts')

@section('content')
    <h2>Emergency alerts: {{ $mess->name }}</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    @if ($isManager)
        <div class="card">
            <h3>Publish a new alert</h3>
            <form method="POST" action="{{ route('alerts.store') }}">
                @csrf
                <label>Type</label>
                <select name="type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>

                <label>Message</label>
                <textarea name="message" rows="3" maxlength="500" required>{{ old('message') }}</textarea>

                <label>Expires at (optional)</label>
                <input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}">

                <button type="submit" class="btn">Publish alert</button>
            </form>
        </div>
    @endif

    <h3>Active alerts</h3>
    @forelse ($activeAlerts as $alert)
        <div class="card" style="border-color:#b00020">
            <p><strong>{{ ucfirst($alert->type) }}</strong> &middot; {{ $alert->created_at->diffForHumans() }}
                @if ($alert->expires_at) &middot; expires {{ $alert->expires_at->format('d M Y, H:i') }} @endif
            </p>
            <p>{{ $alert->message }}</p>

            @if ($isManager)
                @php $pending = $alert->pendingAcknowledgements(); @endphp
                <p>Acknowledged by {{ $alert->acknowledgements_count }} resident(s).
                    @if ($pending->isNotEmpty())
                        Still waiting for: {{ $pending->pluck('user.name')->join(', ') }}.
                    @else
                        Everyone has acknowledged.
                    @endif
                </p>
                <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">Mark as resolved</button>
                </form>
            @elseif ($alert->isAcknowledgedBy($membership))
                <p>You have acknowledged this alert.</p>
            @else
                <form method="POST" action="{{ route('alerts.acknowledge', $alert) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm">I have seen this</button>
                </form>
            @endif
        </div>
    @empty
        <p>No active alerts right now.</p>
    @endforelse

    @if ($pastAlerts->isNotEmpty())
        <h3>Earlier alerts</h3>
        <table>
            <tr><th>Type</th><th>Message</th><th>Status</th><th>Published</th></tr>
            @foreach ($pastAlerts as $alert)
                <tr>
                    <td>{{ ucfirst($alert->type) }}</td>
                    <td>{{ $alert->message }}</td>
                    <td>{{ $alert->stateLabel() }}</td>
                    <td>{{ $alert->created_at->format('d M Y, H:i') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
