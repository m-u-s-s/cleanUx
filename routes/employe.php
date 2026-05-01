<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StripeConnectController;
use App\Livewire\FeedbacksEmploye;
use App\Livewire\Employe\ValidationMultipleRdv;

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

        Route::get('/chef-equipe', function () {
            abort_unless(auth()->user()?->isFieldTeamLead(), 403);

            return app(\App\Livewire\Employe\TeamLeadOperationsCenter::class);
        })->name('teamlead.operations');

        Route::get('/chef-equipe', \App\Livewire\Employe\TeamLeadOperationsCenter::class)
            ->middleware('field.team.lead')
            ->name('teamlead.operations');

        if (class_exists(StripeConnectController::class)) {
            Route::get('/stripe-connect/start', [StripeConnectController::class, 'start'])
                ->name('stripe-connect.start');

            Route::get('/stripe-connect/refresh', [StripeConnectController::class, 'refresh'])
                ->name('stripe-connect.refresh');

            Route::get('/stripe-connect/return', [StripeConnectController::class, 'return'])
                ->name('stripe-connect.return');
        }

        if (class_exists(FeedbacksEmploye::class)) {
            Route::get('/feedbacks', FeedbacksEmploye::class)->name('feedbacks');
        }

        if (class_exists(ValidationMultipleRdv::class)) {
            Route::get('/validation-multiple-rdv', ValidationMultipleRdv::class)
                ->name('validation.multiple');
        }
    });
