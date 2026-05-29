<?php

use App\Http\Controllers\StripeConnectController;
use App\Livewire\Employe\CoordinationChantier;
use App\Livewire\Employe\DisponibilitesEmploye;
use App\Livewire\Employe\EmployeeRateClient;
use App\Livewire\Employe\EquipeTerrain;
use App\Livewire\Employe\HistoriqueEmploye;
use App\Livewire\Employe\MissionFieldPage;
use App\Livewire\Employe\MissionsEmploye;
use App\Livewire\Employe\PlanningEmploye;
use App\Livewire\Employe\SignalerIncident;
use App\Livewire\Employe\TeamLeadOperationsCenter;
use App\Livewire\Employe\ValidationMultipleRdv;
use App\Livewire\EmployeDashboard;
use App\Livewire\FeedbacksEmploye;
use App\Livewire\Provider\ProviderBadgesPage;
use App\Livewire\Provider\ProviderDisputesPage;
use App\Livewire\Provider\ProviderEarningsDashboard;
use App\Livewire\Provider\ProviderKycPage;
use App\Livewire\Provider\ProviderRatingsPage;
use App\Livewire\Provider\ProviderWalletPage;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:employe'])
    ->prefix('dashboard/employe')
    ->name('employe.')
    ->group(function () {

        Route::get('/', EmployeDashboard::class)->name('dashboard');

        if (class_exists(ProviderRatingsPage::class)) {
            Route::get('/avis', ProviderRatingsPage::class)->name('ratings');
        }

        if (class_exists(ProviderWalletPage::class)) {
            Route::get('/portefeuille', ProviderWalletPage::class)->name('wallet');
        }

        if (class_exists(ProviderDisputesPage::class)) {
            Route::get('/litiges', ProviderDisputesPage::class)->name('disputes');
        }

        if (class_exists(ProviderKycPage::class)) {
            Route::get('/verification', ProviderKycPage::class)->name('kyc');
        }

        if (class_exists(MissionsEmploye::class)) {
            Route::get('/missions', MissionsEmploye::class)->name('missions');
        }

        if (class_exists(EmployeeRateClient::class)) {
            Route::get('/missions/{bookingId}/evaluer-client', EmployeeRateClient::class)
                ->name('rate.client');
        }

        if (class_exists(ProviderBadgesPage::class)) {
            Route::get('/badges', ProviderBadgesPage::class)
                ->name('badges');
        }

        if (class_exists(ProviderEarningsDashboard::class)) {
            Route::get('/revenus', ProviderEarningsDashboard::class)
                ->name('earnings');
        }

        if (class_exists(MissionFieldPage::class)) {
            Route::get('/missions/{mission}', MissionFieldPage::class)
                ->middleware('can:update,mission')
                ->name('missions.show');
        }

        if (class_exists(DisponibilitesEmploye::class)) {
            Route::get('/disponibilites', DisponibilitesEmploye::class)->name('disponibilites');
        }

        if (class_exists(PlanningEmploye::class)) {
            Route::get('/planning', PlanningEmploye::class)->name('planning');
        }

        if (class_exists(HistoriqueEmploye::class)) {
            Route::get('/historique', HistoriqueEmploye::class)->name('historique');
        }

        if (class_exists(SignalerIncident::class)) {
            Route::get('/incident', SignalerIncident::class)->name('incident');
        }

        if (class_exists(EquipeTerrain::class)) {
            Route::get('/equipe', EquipeTerrain::class)->name('team');
        }

        if (class_exists(CoordinationChantier::class)) {
            Route::get('/coordination', CoordinationChantier::class)->name('coordination');
        }

        Route::get('/chef-equipe', TeamLeadOperationsCenter::class)
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
