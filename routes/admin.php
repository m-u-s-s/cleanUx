<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\AdminDashboard;
use App\Livewire\Admin\MissionsAdmin;
use App\Http\Controllers\Admin\MissionAdminController;

Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {

    Route::get('/dashboard', AdminDashboard::class)->name('dashboard');

    Route::get('/missions', MissionsAdmin::class)->name('missions');

    Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])
        ->middleware('can:view,mission')
        ->name('missions.show');

});