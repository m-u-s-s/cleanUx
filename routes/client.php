<?php

use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\Client\MissionLiveTracking;
use App\Livewire\ClientDashboard;
use App\Livewire\Client\ClientFeedbackForm;
use Illuminate\Support\Facades\Route;
use App\Livewire\Conversations\ConversationPage;

Route::middleware(['role:client'])
    ->prefix('dashboard/client')
    ->name('client.')
    ->group(function () {
        Route::get('/', ClientDashboard::class)
            ->name('dashboard');

        Route::get('/rendez-vous', MesRendezVousClient::class)
            ->name('rendezvous.index');
        Route::get('/missions/{mission}/tracking', MissionLiveTracking::class)->name('missions.tracking');
        Route::get('/rendez-vous/{rendezVous}/feedback', ClientFeedbackForm::class)->name('feedback.create');
        Route::get('/conversations/{conversation}', ConversationPage::class)->name('conversations.show');
        
    });
