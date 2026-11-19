<?php
use App\Http\Controllers\MealController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessController;
use App\Http\Middleware\EnsureManager;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\JoinRequestController;
use App\Http\Controllers\EmergencyAlertController;
use App\Http\Controllers\DutyTradeRequestController;
use App\Http\Controllers\TaskAssignmentController;
use App\Http\Controllers\TaskTypeController;
use App\Http\Middleware\EnsureActiveMembership;
use App\Http\Middleware\EnsureManagerOrRoomLeader;
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

    // F9 Household task assignment (Raziur)
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/tasks', [TaskAssignmentController::class, 'index'])->name('tasks.index');
        Route::post('/tasks/{taskAssignment}/progress', [TaskAssignmentController::class, 'updateProgress'])->name('tasks.progress');
    });

    Route::middleware(EnsureManagerOrRoomLeader::class)->group(function () {
        Route::post('/tasks', [TaskAssignmentController::class, 'store'])->name('tasks.store');
        Route::post('/tasks/{taskAssignment}/verify', [TaskAssignmentController::class, 'verify'])->name('tasks.verify');
        Route::post('/tasks/{taskAssignment}/missed', [TaskAssignmentController::class, 'markMissed'])->name('tasks.missed');
    });

    Route::middleware(EnsureManager::class)->group(function () {
        Route::get('/task-types', [TaskTypeController::class, 'index'])->name('task-types.index');
        Route::post('/task-types', [TaskTypeController::class, 'store'])->name('task-types.store');
        Route::put('/task-types/{taskType}', [TaskTypeController::class, 'update'])->name('task-types.update');
        Route::post('/task-types/{taskType}/toggle', [TaskTypeController::class, 'toggle'])->name('task-types.toggle');
    });

    // F10 Task tracking and duty exchange (Raziur)
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/duty-trades', [DutyTradeRequestController::class, 'index'])->name('duty-trades.index');
        Route::post('/duty-trades', [DutyTradeRequestController::class, 'store'])->name('duty-trades.store');
        Route::post('/duty-trades/{dutyTradeRequest}/accept', [DutyTradeRequestController::class, 'accept'])->name('duty-trades.accept');
        Route::post('/duty-trades/{dutyTradeRequest}/reject', [DutyTradeRequestController::class, 'reject'])->name('duty-trades.reject');
        Route::post('/duty-trades/{dutyTradeRequest}/cancel', [DutyTradeRequestController::class, 'cancel'])->name('duty-trades.cancel');
    });

    Route::middleware(EnsureManagerOrRoomLeader::class)->group(function () {
        Route::post('/duty-trades/{dutyTradeRequest}/approve', [DutyTradeRequestController::class, 'approve'])->name('duty-trades.approve');
    });
 
    // F5 Daily meal on/off and guest meals (Prithu)
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/meals', [MealController::class, 'index'])->name('meals.index');
        Route::post('/meals/toggle', [MealController::class, 'toggle'])->name('meals.toggle');
        Route::post('/meals/guest', [MealController::class, 'storeGuest'])->name('meals.guest.store');
        Route::post('/meals/guest/{guestMeal}/cancel', [MealController::class, 'cancelGuest'])->name('meals.guest.cancel');
    });
});

    // F2 Utility and bazar expenses (Prithu)
    Route::middleware(EnsureActiveMembership::class)->group(function () {
        Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    });

    Route::middleware(EnsureManager::class)->group(function () {
        Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    });
});
 
