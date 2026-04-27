<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MissionTrackingController;
use App\Http\Controllers\MissionFieldActionController;

/*
|--------------------------------------------------------------------------
| Mission actions
|--------------------------------------------------------------------------
*/

// EMPLOYÉ UNIQUEMENT
Route::middleware(['role:employe'])->group(function () {

    Route::post('/missions/{mission}/arrived', [MissionFieldActionController::class, 'arrived']);

    Route::post('/missions/{mission}/tracking/start', [MissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/tracking/push', [MissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/tracking/stop', [MissionTrackingController::class, 'stop']);
});

// CLIENT + ADMIN
Route::get('/missions/{mission}/tracking/live', [MissionTrackingController::class, 'live'])
    ->middleware('auth');