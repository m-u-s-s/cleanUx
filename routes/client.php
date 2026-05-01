<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['role:client'])
    ->prefix('dashboard/client')
    ->name('client.')
    ->group(function () {

        Route::get('/', \App\Livewire\ClientDashboard::class)->name('dashboard');

        if (class_exists(\App\Livewire\Client\MesRendezVousClient::class)) {
            Route::get('/rendez-vous', \App\Livewire\Client\MesRendezVousClient::class)->name('rendezvous.index');
        }

        if (class_exists(\App\Livewire\Client\PrendreRendezVous::class)) {
            Route::get('/rendez-vous/nouveau', \App\Livewire\Client\PrendreRendezVous::class)->name('rendezvous.create');
        }

        if (class_exists(\App\Livewire\Client\MissionLiveTracking::class)) {
            Route::get('/missions/{mission}/tracking', \App\Livewire\Client\MissionLiveTracking::class)->name('missions.tracking');
        }

        if (class_exists(\App\Livewire\Client\ClientFeedbackForm::class)) {
            Route::get('/rendez-vous/{rendezVous}/feedback', \App\Livewire\Client\ClientFeedbackForm::class)->name('feedback.create');
        }

        if (class_exists(\App\Livewire\Conversations\ConversationPage::class)) {
            Route::get('/conversations/{conversation}', \App\Livewire\Conversations\ConversationPage::class)->name('conversations.show');
        }

        if (class_exists(\App\Livewire\Client\WalletClient::class)) {
            Route::get('/portefeuille', \App\Livewire\Client\WalletClient::class)->name('wallet');
        }

        if (class_exists(\App\Livewire\Client\LitigesClient::class)) {
            Route::get('/litiges', \App\Livewire\Client\LitigesClient::class)->name('claims');
        }

        if (class_exists(\App\Livewire\Client\FinanceDocumentsClient::class)) {
            Route::get('/finance', \App\Livewire\Client\FinanceDocumentsClient::class)->name('finance');
        }

        if (class_exists(\App\Livewire\Client\ProfilClient::class)) {
            Route::get('/profil', \App\Livewire\Client\ProfilClient::class)->name('profile');
        }

        if (class_exists(\App\Livewire\Client\FavoriteEmployesManager::class)) {
            Route::get('/favoris-employes', \App\Livewire\Client\FavoriteEmployesManager::class)->name('favorite-employes');
        }

        if (class_exists(\App\Livewire\Client\HistoriqueClient::class)) {
            Route::get('/historique', \App\Livewire\Client\HistoriqueClient::class)->name('historique');
        }

        if (class_exists(\App\Livewire\Client\ClientSubscriptions::class)) {
            Route::get('/abonnements', \App\Livewire\Client\ClientSubscriptions::class)
                ->name('subscriptions');
        }

        Route::get('/rendez-vous/series/{series}/edit', function ($series) {
            abort_unless(auth()->user()?->isClient(), 403);

            return view('client.recurring-series-edit', [
                'series' => $series,
            ]);
        })->name('rendezvous.series.edit');
    });
