@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <h2>Log in</h2>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email') <div class="error">{{ $message }}</div> @enderror

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>

        <label><input type="checkbox" name="remember"> Remember me</label>

        <button type="submit" class="btn">Log in</button>
    </form>
    <p>No account? <a href="{{ route('register') }}">Register</a></p>
@endsection