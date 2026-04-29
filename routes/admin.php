<?php

use App\Http\Controllers\Admin\MissionAdminController;
use App\Livewire\Admin\MissionsAdmin;
use App\Livewire\AdminDashboard;
use App\Livewire\Admin\CustomerCreditsManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboard::class)
            ->name('dashboard');

        Route::get('/missions', MissionsAdmin::class)
            ->name('missions');

        Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])
            ->middleware('can:view,mission')
            ->name('missions.show');

        Route::get('/credits-clients', CustomerCreditsManager::class)
            ->middleware('can:manage-finance')
            ->name('customer.credits');
    });
