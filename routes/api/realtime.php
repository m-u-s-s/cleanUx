<?php

use App\Http\Controllers\Api\Realtime\SocketConfigController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Sprint 0 — Task 4: Realtime / WebSocket (mobile)
// ─────────────────────────────────────────────
// GET  /api/realtime/socket-config   → returns Reverb connection params (no secret)
// POST /api/broadcasting/auth        → channel auth via Bearer (mobile-safe, no session cookie)
// Option (a): web route /broadcasting/auth remains for Livewire/Echo-web.
//             Mobile uses /api/broadcasting/auth with Sanctum Bearer token.

Route::middleware(['auth:sanctum', 'active.account', 'token.grace'])->group(function () {
    Route::get('/realtime/socket-config',
        SocketConfigController::class);

    Route::post('/broadcasting/auth',
        [BroadcastController::class, 'authenticate']);
});
