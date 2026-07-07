<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|─────────────────────────────────────────────────────────────────────────────
| API Routes – E-Ticketing Helpdesk
|─────────────────────────────────────────────────────────────────────────────
| Base URL  : http://your-domain.com/api
| Auth      : Laravel Sanctum (Bearer token)
| Headers   : Accept: application/json
|             Authorization: Bearer {token}
*/

// ── Public routes (no auth required) ─────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register',        [AuthController::class, 'register']);
    Route::post('login',           [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password',  [AuthController::class, 'resetPassword']);
});

// ── Protected routes (Sanctum auth required) ──────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/',                [ProfileController::class, 'show']);
        Route::put('/',                [ProfileController::class, 'update']);
        Route::put('change-password',  [ProfileController::class, 'changePassword']);
    });

    Route::get('/users', [UserController::class, 'index']);
    // Helpdesk list (for ticket assignment — helpdesk & admin only)
    Route::get('helpdesks', [ProfileController::class, 'helpdesks']);

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('stats',          [DashboardController::class, 'stats']);
        Route::get('statistics',     [DashboardController::class, 'statistics']);
        Route::get('recent-tickets', [DashboardController::class, 'recentTickets']);
    });

    // Tickets
    Route::prefix('tickets')->group(function () {
        Route::get('/',                        [TicketController::class, 'index']);
        Route::post('/',                       [TicketController::class, 'store']);
        Route::get('/{id}',                    [TicketController::class, 'show']);
        Route::get('/{id}/history',            [TicketController::class, 'history']);
        Route::patch('/{id}/assign',           [TicketController::class, 'assign']);
        Route::patch('/{id}/finish',           [TicketController::class, 'finish']);
        Route::delete('/{id}',                 [TicketController::class, 'destroy']);
        // Comments (nested under ticket)
        Route::get('/{ticketId}/comments',     [CommentController::class, 'index']);
        Route::post('/{ticketId}/comments',    [CommentController::class, 'store']);
    });

    // Comment delete (admin only — enforced inside controller)
    Route::delete('comments/{id}', [CommentController::class, 'destroy']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'index']);
        Route::patch('read-all',     [NotificationController::class, 'markAllRead']);
        Route::patch('{id}/read',    [NotificationController::class, 'markRead']);
        Route::delete('/',           [NotificationController::class, 'destroyAll']);
        Route::delete('{id}',        [NotificationController::class, 'destroy']);
    });
});
