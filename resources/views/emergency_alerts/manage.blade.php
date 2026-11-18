@extends('layouts.app')

@section('title', 'Emergency alerts')

@section('content')
    <h2>Emergency alerts for {{ $mess->name }}</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    <h3>Publish a new alert</h3>
    <form method="POST" action="{{ route('alerts.store') }}">
        @csrf
        <label for="type">Type</label>
        <select id="type" name="type" required>
            <option value="">Choose...</option>
            @foreach (\App\Models\EmergencyAlert::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type') <div class="error">{{ $message }}</div> @enderror

        <label for="message">Message</label>
        <textarea id="message" name="message" rows="3" maxlength="1000" placeholder="e.g. No water until 6 PM, please store what you need." required>{{ old('message') }}</textarea>
        @error('message') <div class="error">{{ $message }}</div> @enderror

        <label for="duration_hours">Keep active for</label>
        <select id="duration_hours" name="duration_hours" required>
            @foreach (\App\Models\EmergencyAlert::DURATIONS as $hours => $label)
                <option value="{{ $hours }}" @selected((int) old('duration_hours', 6) === $hours)>{{ $label }}</option>
            @endforeach
        </select>
        @error('duration_hours') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn" style="background:#b00020">Publish alert to all residents</button>
    </form>

    <h3>All alerts</h3>
    <table>
        <tr><th>Type</th><th>Message</th><th>Status</th><th>Seen by</th><th></th></tr>
        @forelse ($alerts as $alert)
            <tr>
                <td>{{ $alert->typeLabel() }}<br><small>{{ $alert->created_at->diffForHumans() }}</small></td>
                <td>{{ \Illuminate\Support\Str::limit($alert->message, 80) }}</td>
                <td>{{ $alert->statusLabel() }}</td>
                <td>{{ $alert->acknowledgements_count }} / {{ $memberCount }}</td>
                <td>
                    <a href="{{ route('alerts.show', $alert) }}">Details</a>
                    @if ($alert->isActive())
                        <form method="POST" action="{{ route('alerts.resolve', $alert) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm">Resolve</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No alerts have been published yet.</td></tr>
        @endforelse
    </table>

    <p><a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
