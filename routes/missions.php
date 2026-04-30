<?php

use App\Http\Controllers\MissionFieldActionController;
use App\Http\Controllers\MissionTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:employe'])->group(function () {
    Route::post('/missions/offline-sync', [MissionFieldActionController::class, 'offlineSync'])
        ->name('missions.offline-sync');

    Route::post('/mission-checklist-items/{item}/toggle', [MissionFieldActionController::class, 'toggleChecklistItem'])
        ->name('missions.checklist-items.toggle');

    Route::post('/missions/{mission}/start', [MissionFieldActionController::class, 'start'])
        ->name('missions.start');

    Route::post('/missions/{mission}/en-route', [MissionFieldActionController::class, 'enRoute'])
        ->name('missions.en-route');

    Route::post('/missions/{mission}/arrived', [MissionFieldActionController::class, 'arrived'])
        ->name('missions.arrived');

    Route::post('/missions/{mission}/finish', [MissionFieldActionController::class, 'finish'])
        ->name('missions.finish');

    Route::post('/missions/{mission}/tracking/start', [MissionTrackingController::class, 'start'])
        ->middleware('can:track,mission')
        ->name('missions.tracking.start');

    Route::post('/mission-tracking-sessions/{session}/tracking/push', [MissionTrackingController::class, 'push'])
        ->name('missions.tracking.push');

    Route::post('/mission-tracking-sessions/{session}/tracking/stop', [MissionTrackingController::class, 'stop'])
        ->name('missions.tracking.stop');
});

Route::get('/missions/{mission}/tracking/live', [MissionTrackingController::class, 'live'])
    ->middleware('can:view,mission')
    ->name('missions.tracking.live');