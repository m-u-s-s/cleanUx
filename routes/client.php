<?php

use App\Models\FinanceInvoice;
use App\Models\FinanceQuote;
use Illuminate\Support\Facades\Response;
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

        Route::get('/finance/devis/{quote}/telecharger', function (FinanceQuote $quote) {
            $user = auth()->user();

            abort_unless($user && (int) $quote->client_id === (int) $user->id, 403);

            $path = $quote->getAttribute('pdf_path')
                ?? $quote->getAttribute('document_path')
                ?? $quote->getAttribute('file_path');

            abort_unless(filled($path), 404, 'PDF du devis introuvable.');

            $fullPath = storage_path('app/public/' . ltrim($path, '/'));

            abort_unless(file_exists($fullPath), 404, 'Fichier du devis introuvable.');

            return Response::download($fullPath);
        })->name('finance.quote.download');

        Route::get('/finance/factures/{invoice}/telecharger', function (FinanceInvoice $invoice) {
            $user = auth()->user();

            abort_unless($user && (int) $invoice->client_id === (int) $user->id, 403);

            $path = $invoice->getAttribute('pdf_path')
                ?? $invoice->getAttribute('document_path')
                ?? $invoice->getAttribute('file_path');

            abort_unless(filled($path), 404, 'PDF de la facture introuvable.');

            $fullPath = storage_path('app/public/' . ltrim($path, '/'));

            abort_unless(file_exists($fullPath), 404, 'Fichier de la facture introuvable.');

            return Response::download($fullPath);
        })->name('finance.invoice.download');

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
    });
