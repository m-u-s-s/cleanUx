<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeMissionTrackingController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// route mission tracking
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/missions/{mission}/tracking/start', [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push', [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop', [EmployeeMissionTrackingController::class, 'stop']);
});