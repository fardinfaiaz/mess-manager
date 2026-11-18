@extends('layouts.app')

@section('title', 'Notice board')

@section('content')
    <h2>Notice board: {{ $mess->name }}</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    <h3>Publish a notice</h3>
    <form method="POST" action="{{ route('notices.store') }}">
        @csrf
        @include('notices._form_fields', ['notice' => null])
        <button type="submit" class="btn">Publish notice</button>
    </form>

    <h3>Current notices</h3>
    <form method="GET" action="{{ route('notices.index') }}">
        <select name="category" onchange="this.form.submit()">
            <option value="">All categories</option>
            @foreach (\App\Models\Notice::CATEGORIES as $value => $label)
                <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <tr><th>Notice</th><th>Audience</th><th></th></tr>
        @forelse ($notices as $notice)
            <tr>
                <td>
                    @if ($notice->pinned) &#128204; @endif<a href="{{ route('notices.show', $notice) }}">{{ $notice->title }}</a><br>
                    <small>{{ $notice->categoryLabel() }}@if ($notice->expires_at) &middot; until {{ $notice->expires_at->format('d M, H:i') }}@endif</small>
                </td>
                <td>{{ $notice->audienceLabel() }}</td>
                <td>
                    <a href="{{ route('notices.edit', $notice) }}">Edit</a>
                    <form method="POST" action="{{ route('notices.pin', $notice) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm">{{ $notice->pinned ? 'Unpin' : 'Pin' }}</button>
                    </form>
                    <form method="POST" action="{{ route('notices.destroy', $notice) }}" onsubmit="return confirm('Remove this notice?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm" style="background:#b00020">Remove</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="3">No current notices.</td></tr>
        @endforelse
    </table>

    <p><a href="{{ route('notices.archive') }}">Previous notices</a> &middot; <a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
