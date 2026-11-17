@extends('layouts.app')

@section('title', 'Create a mess')

@section('content')
    <h2>Create a mess</h2>
    <p>You will become the manager of this mess.</p>
    <form method="POST" action="{{ route('messes.store') }}">
        @csrf
        <label for="name">Mess name</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
        @error('name') <div class="error">{{ $message }}</div> @enderror

        <label for="address">Address</label>
        <input type="text" id="address" name="address" value="{{ old('address') }}" required>
        @error('address') <div class="error">{{ $message }}</div> @enderror

        <label for="area">Area</label>
        <input type="text" id="area" name="area" value="{{ old('area') }}" placeholder="e.g. Mohakhali" required>
        @error('area') <div class="error">{{ $message }}</div> @enderror

        <button type="submit" class="btn">Create mess</button>
    </form>
@endsection