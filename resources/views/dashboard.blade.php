@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h2>Welcome, {{ $user->name }}</h2>
    <p>You are logged in to Mess-Manager.</p>
    <p>Mess features (meals, expenses, duties) will appear here from Sprint 1.</p>
@endsection