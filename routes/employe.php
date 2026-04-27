<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\EmployeDashboard;
use App\Livewire\Employe\MissionsEmploye;

Route::middleware(['role:employe'])->prefix('dashboard/employe')->name('employe.')->group(function () {

    Route::get('/', EmployeDashboard::class)->name('dashboard');

    Route::get('/missions', MissionsEmploye::class)->name('missions');

});