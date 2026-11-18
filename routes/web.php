<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessController;
use App\Http\Middleware\EnsureManager;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\JoinRequestController;
use App\Http\Controllers\EmergencyAlertController;
use App\Http\Controllers\NoticeController;

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

    // F14 Emergency announcements - all members (Labonno)
    Route::get('/alerts', [EmergencyAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/{alert}/acknowledge', [EmergencyAlertController::class, 'acknowledge'])->name('alerts.acknowledge');

    // F20 Notice board - all members (Labonno). 'archive' is declared before '{notice}' on purpose.
    Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
    Route::get('/notices/archive', [NoticeController::class, 'archive'])->name('notices.archive');
    Route::get('/notices/{notice}', [NoticeController::class, 'show'])->name('notices.show');

    // F8 Room roster (Fardin) - manager only
    Route::middleware(EnsureManager::class)->group(function () {
        Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
        Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
        Route::get('/rooms/{room}/edit', [RoomController::class, 'edit'])->name('rooms.edit');
        Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
        Route::post('/rooms/assign', [RoomController::class, 'assignMember'])->name('rooms.assign');
        Route::post('/rooms/{room}/leader', [RoomController::class, 'setLeader'])->name('rooms.leader');

        // F14 Emergency announcements - manager side (Labonno)
        Route::post('/alerts', [EmergencyAlertController::class, 'store'])->name('alerts.store');
        Route::get('/alerts/{alert}', [EmergencyAlertController::class, 'show'])->name('alerts.show');
        Route::post('/alerts/{alert}/resolve', [EmergencyAlertController::class, 'resolve'])->name('alerts.resolve');

        // F20 Notice board - manager side (Labonno)
        Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
        Route::get('/notices/{notice}/edit', [NoticeController::class, 'edit'])->name('notices.edit');
        Route::put('/notices/{notice}', [NoticeController::class, 'update'])->name('notices.update');
        Route::delete('/notices/{notice}', [NoticeController::class, 'destroy'])->name('notices.destroy');
        Route::post('/notices/{notice}/pin', [NoticeController::class, 'togglePin'])->name('notices.pin');

        // F1 Join requests - manager side (Fardin)
        Route::get('/join-requests', [JoinRequestController::class, 'index'])->name('join.index');
        Route::post('/join-requests/{joinRequest}/approve', [JoinRequestController::class, 'approve'])->name('join.approve');
        Route::post('/join-requests/{joinRequest}/reject', [JoinRequestController::class, 'reject'])->name('join.reject');
    });
});