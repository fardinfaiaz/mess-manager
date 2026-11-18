@extends('layouts.app')

@section('title', 'Notice board')

@section('content')
    <h2>Notice board: {{ $mess->name }}</h2>

    <form method="GET" action="{{ route('notices.index') }}">
        <label for="category">Filter by category</label>
        <select id="category" name="category" onchange="this.form.submit()">
            <option value="">All notices</option>
            @foreach (\App\Models\Notice::CATEGORIES as $value => $label)
                <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    @forelse ($notices as $notice)
        @include('notices._card', ['notice' => $notice])
    @empty
        <p>No current notices.</p>
    @endforelse

    <p><a href="{{ route('notices.archive') }}">Previous notices</a> &middot; <a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
