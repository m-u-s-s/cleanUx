<?php

use App\Http\Controllers\Analytics\AnalyticsExportController;
use App\Http\Controllers\Client\ClientExcelExportController;
use App\Http\Controllers\Client\FinanceDocumentDownloadController;
use App\Livewire\Client\AiQuotePhoto;
use App\Livewire\Client\BookingCheckout;
use App\Livewire\Client\BrowseProviders;
use App\Livewire\Client\Calendar\ClientCalendarFC;
use App\Livewire\Client\ClientApiTokens;
use App\Livewire\Client\ClientChatInbox;
use App\Livewire\Client\ClientContractSign;
use App\Livewire\Client\ClientFeedbackForm;
use App\Livewire\Client\ClientKybOnboarding;
use App\Livewire\Client\ClientLiveTrackingMap;
use App\Livewire\Client\ClientLoyaltyRewards;
use App\Livewire\Client\ClientSubscriptions;
use App\Livewire\Client\ClientSubscriptionsV2;
use App\Livewire\Client\ClientTipBooking;
use App\Livewire\Client\FavoriteEmployesManager;
use App\Livewire\Client\FinanceDocumentsClient;
use App\Livewire\Client\GdprDataPage;
use App\Livewire\Client\HistoriqueClient;
use App\Livewire\Client\LitigesClient;
use App\Livewire\Client\LoyaltyDashboard;
use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\Client\MissionLiveTracking;
use App\Livewire\Client\MultiTradesBundleManager;
use App\Livewire\Client\NpsSurvey;
use App\Livewire\Client\PrendreRendezVous;
use App\Livewire\Client\ProfilClient;
use App\Livewire\Client\ProfileEdit;
use App\Livewire\Client\ReferralProgramPage;
use App\Livewire\Client\SavedPaymentMethods;
use App\Livewire\Client\Templates\RecurringTemplatesGallery;
use App\Livewire\Client\WalletClient;
use App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard;
use App\Livewire\ClientDashboard;
use App\Livewire\Conversations\ConversationPage;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:client'])
    ->prefix('dashboard/client')
    ->name('client.')
    ->group(function () {

        Route::get('/', ClientDashboard::class)->name('dashboard');

        if (class_exists(MesRendezVousClient::class)) {
            Route::get('/rendez-vous', MesRendezVousClient::class)->name('rendezvous.index');
        }

        if (class_exists(PrendreRendezVous::class)) {
            Route::get('/rendez-vous/nouveau', PrendreRendezVous::class)->name('rendezvous.create');
        }

        if (class_exists(BrowseProviders::class)) {
            Route::get('/prestataires', BrowseProviders::class)->name('providers.browse');
        }

        if (class_exists(GdprDataPage::class)) {
            Route::get('/donnees', GdprDataPage::class)->name('gdpr.data');
        }

        if (class_exists(LoyaltyDashboard::class)) {
            Route::get('/fidelite', LoyaltyDashboard::class)->name('loyalty');
        }

        if (class_exists(MissionLiveTracking::class)) {
            Route::get('/missions/{mission}/tracking', MissionLiveTracking::class)->name('missions.tracking');
        }

        if (class_exists(ClientFeedbackForm::class)) {
            Route::get('/rendez-vous/{rendezVous}/feedback', ClientFeedbackForm::class)->name('feedback.create');
        }

        if (class_exists(ConversationPage::class)) {
            Route::get('/conversations/{conversation}', ConversationPage::class)->name('conversations.show');
        }

        if (class_exists(ReferralProgramPage::class)) {
            Route::get('/parrainage', ReferralProgramPage::class)->name('referrals');
        }

        if (class_exists(WalletClient::class)) {
            Route::get('/portefeuille', WalletClient::class)->name('wallet');
        }

        if (class_exists(LitigesClient::class)) {
            Route::get('/litiges', LitigesClient::class)->name('claims');
        }

        if (class_exists(FinanceDocumentsClient::class)) {
            Route::get('/finance', FinanceDocumentsClient::class)->name('finance');
        }

        if (class_exists(ProfilClient::class)) {
            Route::get('/profil', ProfilClient::class)->name('profile');
        }

        if (class_exists(FavoriteEmployesManager::class)) {
            Route::get('/favoris-employes', FavoriteEmployesManager::class)->name('favorite-employes');
        }

        if (class_exists(HistoriqueClient::class)) {
            Route::get('/historique', HistoriqueClient::class)->name('historique');
        }

        if (class_exists(ClientSubscriptions::class)) {
            Route::get('/abonnements', ClientSubscriptions::class)
                ->name('subscriptions');
        }

        if (class_exists(ClientSubscriptionsV2::class)) {
            Route::get('/abonnements-v2', ClientSubscriptionsV2::class)
                ->name('subscriptions-v2');
        }

        if (class_exists(ClientChatInbox::class)) {
            Route::get('/messagerie', ClientChatInbox::class)
                ->name('chat.inbox');
        }

        if (class_exists(ClientApiTokens::class)) {
            Route::get('/api-tokens', ClientApiTokens::class)
                ->name('api-tokens');
        }

        if (class_exists(ClientKybOnboarding::class)) {
            Route::get('/kyb-onboarding', ClientKybOnboarding::class)
                ->name('kyb.onboarding');
        }

        if (class_exists(ClientContractSign::class)) {
            Route::get('/contrats', ClientContractSign::class)
                ->name('contracts');
        }

        if (class_exists(ClientLoyaltyRewards::class)) {
            Route::get('/fidelite/recompenses', ClientLoyaltyRewards::class)
                ->name('loyalty.rewards');
        }

        if (class_exists(ClientTipBooking::class)) {
            Route::get('/missions/{bookingId}/pourboire', ClientTipBooking::class)
                ->name('tip.booking');
        }

        if (class_exists(BookingCheckout::class)) {
            Route::get('/missions/{bookingId}/checkout', BookingCheckout::class)
                ->name('booking.checkout');
        }

        if (class_exists(SavedPaymentMethods::class)) {
            Route::get('/paiement/cartes', SavedPaymentMethods::class)
                ->name('payment.methods');
        }

        if (class_exists(ProfileEdit::class)) {
            Route::get('/profil/editer', ProfileEdit::class)
                ->name('profile.edit');
        }

        if (class_exists(ClientLiveTrackingMap::class)) {
            Route::get('/missions/{bookingId}/tracking-map', ClientLiveTrackingMap::class)
                ->name('booking.tracking.map');
        }

        if (class_exists(NpsSurvey::class)) {
            Route::get('/nps', NpsSurvey::class)->name('nps.survey');
        }

        if (class_exists(MultiTradesBundleManager::class)) {
            Route::get('/chantiers-groupes', MultiTradesBundleManager::class)->name('bundles.manage');
        }

        if (class_exists(AiQuotePhoto::class)) {
            Route::get('/devis-ia', AiQuotePhoto::class)->name('ai.quote.photo');
        }

        // PDF finance — URL signée expirante (défense en profondeur, en plus de l'ownership).
        Route::get('/finance/devis/{quote}/telecharger', [FinanceDocumentDownloadController::class, 'quote'])
            ->middleware('signed')
            ->name('finance.quote.download');

        Route::get('/finance/factures/{invoice}/telecharger', [FinanceDocumentDownloadController::class, 'invoice'])
            ->middleware('signed')
            ->name('finance.invoice.download');

        // Phase 7 — Dashboard analytics
        if (class_exists(ClientAnalyticsDashboard::class)) {
            Route::get('/analytics', ClientAnalyticsDashboard::class)
                ->name('analytics.dashboard');
        }
        // Phase 6.1 — Calendrier interactif (drag-and-drop)
        if (class_exists(ClientCalendarFC::class)) {
            Route::get('/calendrier/interactif', ClientCalendarFC::class)
                ->name('calendar.interactive');
        }

        // Phase 6.1 — Galerie de templates
        if (class_exists(RecurringTemplatesGallery::class)) {
            Route::get('/recurrences/templates', RecurringTemplatesGallery::class)
                ->name('recurring.templates');
        }

        // Phase 6.1 — Export Excel multi-onglets
        Route::get('/exports/bookings.xlsx', [ClientExcelExportController::class, 'bookings'])
            ->name('exports.bookings.xlsx');

        // Phase 7 — Exports analytics (CSV)
        Route::prefix('analytics/exports')->name('analytics.export.')->group(function () {
            Route::get('/kpis.csv', [AnalyticsExportController::class, 'kpis'])
                ->name('kpis');
            Route::get('/monthly-revenue.csv', [AnalyticsExportController::class, 'monthlyRevenue'])
                ->name('monthly_revenue');
            Route::get('/bookings.csv', [AnalyticsExportController::class, 'bookings'])
                ->name('bookings');
        });
    });
