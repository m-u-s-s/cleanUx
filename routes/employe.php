<?php

use App\Livewire\Employe\MissionsEmploye;
use App\Livewire\EmployeDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:employe'])
    ->prefix('dashboard/employe')
    ->name('employe.')
    ->group(function () {
        Route::get('/', EmployeDashboard::class)
            ->name('dashboard');

        Route::get('/missions', MissionsEmploye::class)
            ->name('missions');
    });