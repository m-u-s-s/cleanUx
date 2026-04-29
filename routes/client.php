<?php

use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\ClientDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:client'])
    ->prefix('dashboard/client')
    ->name('client.')
    ->group(function () {
        Route::get('/', ClientDashboard::class)
            ->name('dashboard');

        Route::get('/rendez-vous', MesRendezVousClient::class)
            ->name('rendezvous.index');
    });