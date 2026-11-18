@extends('layouts.app')

@section('title', 'Previous notices')

@section('content')
    <h2>Previous notices: {{ $mess->name }}</h2>

    @forelse ($notices as $notice)
        @include('notices._card', ['notice' => $notice])
    @empty
        <p>No previous notices.</p>
    @endforelse

    <p><a href="{{ route('notices.index') }}">Current notices</a> &middot; <a href="{{ route('dashboard') }}">Back to dashboard</a></p>
@endsection
