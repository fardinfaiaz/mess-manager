<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessController;
use App\Http\Middleware\EnsureManager;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\JoinRequestController;

Route::get('/', fn () => redirect()->route('dashboard'));

// Pages for visitors who are NOT logged in
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Pages only for logged-in users
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Mess setup (Fardin)
    Route::get('/messes/create', [MessController::class, 'create'])->name('messes.create');
    Route::post('/messes', [MessController::class, 'store'])->name('messes.store');

    // F1 Join requests - visitor side (Fardin)
    Route::get('/messes', [JoinRequestController::class, 'search'])->name('messes.search');
    Route::post('/messes/{mess}/join', [JoinRequestController::class, 'store'])->name('join.store');

    // F8 Room roster (Fardin) - manager only
    Route::middleware(EnsureManager::class)->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::post('/rooms/assign', [RoomController::class, 'assignMember'])->name('rooms.assign');
        Route::post('/rooms/{room}/leader', [RoomController::class, 'setLeader'])->name('rooms.leader');

        // F1 Join requests - manager side (Fardin)
        Route::get('/join-requests', [JoinRequestController::class, 'index'])->name('join.index');
        Route::post('/join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve'])->name('join.approve');
        Route::post('/join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject'])->name('join.reject');
    });
});