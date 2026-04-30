<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['role:employe'])
    ->prefix('dashboard/employe')
    ->name('employe.')
    ->group(function () {

        Route::get('/', \App\Livewire\EmployeDashboard::class)->name('dashboard');

        if (class_exists(\App\Livewire\Employe\MissionsEmploye::class)) {
            Route::get('/missions', \App\Livewire\Employe\MissionsEmploye::class)->name('missions');
        }

        if (class_exists(\App\Livewire\Employe\MissionFieldPage::class)) {
            Route::get('/missions/{mission}', \App\Livewire\Employe\MissionFieldPage::class)
                ->middleware('can:update,mission')
                ->name('missions.show');
        }

        if (class_exists(\App\Livewire\Employe\DisponibilitesEmploye::class)) {
            Route::get('/disponibilites', \App\Livewire\Employe\DisponibilitesEmploye::class)->name('disponibilites');
        }

        if (class_exists(\App\Livewire\Employe\PlanningEmploye::class)) {
            Route::get('/planning', \App\Livewire\Employe\PlanningEmploye::class)->name('planning');
        }

        if (class_exists(\App\Livewire\Employe\HistoriqueEmploye::class)) {
            Route::get('/historique', \App\Livewire\Employe\HistoriqueEmploye::class)->name('historique');
        }

        if (class_exists(\App\Livewire\Employe\SignalerIncident::class)) {
            Route::get('/incident', \App\Livewire\Employe\SignalerIncident::class)->name('incident');
        }

        if (class_exists(\App\Livewire\Employe\EquipeTerrain::class)) {
            Route::get('/equipe', \App\Livewire\Employe\EquipeTerrain::class)->name('team');
        }

        if (class_exists(\App\Livewire\Employe\CoordinationChantier::class)) {
            Route::get('/coordination', \App\Livewire\Employe\CoordinationChantier::class)->name('coordination');
        }

        if (class_exists(\App\Livewire\Employe\TeamLeadOperationsCenter::class)) {
            Route::get('/chef-equipe', \App\Livewire\Employe\TeamLeadOperationsCenter::class)->name('teamlead.operations');
        }
    });