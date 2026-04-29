<?php

use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/missions/{mission}/tracking/start', [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push', [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop', [EmployeeMissionTrackingController::class, 'stop']);
});