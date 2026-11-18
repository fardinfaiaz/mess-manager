@extends('layouts.app')

@section('title', 'Edit notice')

@section('content')
    <h2>Edit notice</h2>

    @if ($errors->any()) <div class="flash flash-error">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('notices.update', $notice) }}">
        @csrf
        @method('PUT')
        @include('notices._form_fields', ['notice' => $notice])
        <button type="submit" class="btn">Save changes</button>
    </form>

    <p><a href="{{ route('notices.index') }}">Cancel</a></p>
@endsection
