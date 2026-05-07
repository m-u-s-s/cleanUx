<?php

use App\Http\Controllers\Analytics\AnalyticsExportController;
use App\Http\Controllers\Client\ClientExcelExportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\FinanceDocumentDownloadController;

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

        Route::get('/finance/devis/{quote}/telecharger', [FinanceDocumentDownloadController::class, 'quote'])
            ->name('finance.quote.download');

        Route::get('/finance/factures/{invoice}/telecharger', [FinanceDocumentDownloadController::class, 'invoice'])
            ->name('finance.invoice.download');

        // Phase 7 — Dashboard analytics
        if (class_exists(\App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard::class)) {
            Route::get('/analytics', \App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard::class)
                ->name('analytics.dashboard');
        }
        // Phase 6.1 — Calendrier interactif (drag-and-drop)
        if (class_exists(\App\Livewire\Client\Calendar\ClientCalendarFC::class)) {
            Route::get('/calendrier/interactif', \App\Livewire\Client\Calendar\ClientCalendarFC::class)
                ->name('calendar.interactive');
        }

        // Phase 6.1 — Galerie de templates
        if (class_exists(\App\Livewire\Client\Templates\RecurringTemplatesGallery::class)) {
            Route::get('/recurrences/templates', \App\Livewire\Client\Templates\RecurringTemplatesGallery::class)
                ->name('recurring.templates');
        }

        // Phase 6.1 — Export Excel multi-onglets
        Route::get('/exports/bookings.xlsx', [ClientExcelExportController::class, 'bookings'])
            ->name('exports.bookings.xlsx');

        // Phase 7 — Exports analytics (CSV)
        Route::prefix('analytics/exports')->name('analytics.export.')->group(function () {
            Route::get('/kpis.csv',            [AnalyticsExportController::class, 'kpis'])
                ->name('kpis');
            Route::get('/monthly-revenue.csv', [AnalyticsExportController::class, 'monthlyRevenue'])
                ->name('monthly_revenue');
            Route::get('/bookings.csv',        [AnalyticsExportController::class, 'bookings'])
                ->name('bookings');
        });
    });
