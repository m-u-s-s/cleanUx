<?php

use App\Http\Controllers\Analytics\AnalyticsExportController;
use App\Http\Controllers\Client\ClientExcelExportController;
use App\Http\Controllers\Client\FinanceDocumentDownloadController;
use App\Livewire\Client\AiQuotePhoto;
use App\Livewire\Client\BookingCheckout;
use App\Livewire\Client\BrowseCompanies;
use App\Livewire\Client\BrowseProviders;
use App\Livewire\Client\Calendar\ClientCalendarFC;
use App\Livewire\Client\ClientApiTokens;
use App\Livewire\Client\ClientChatInbox;
use App\Livewire\Client\ClientContractSign;
use App\Livewire\Client\ClientFeedbackForm;
use App\Livewire\Client\ClientKybOnboarding;
use App\Livewire\Client\ClientLiveTrackingMap;
use App\Livewire\Client\ClientLoyaltyRewards;
use App\Livewire\Client\ClientSubscriptionsV2;
use App\Livewire\Client\ClientTipBooking;
use App\Livewire\Client\EditRecurringBooking;
use App\Livewire\Client\FinanceDocumentsClient;
use App\Livewire\Client\GdprDataPage;
use App\Livewire\Client\HistoriqueClient;
use App\Livewire\Client\LitigesClient;
use App\Livewire\Client\LoyaltyDashboard;
use App\Livewire\Client\MesRendezVousClient;
use App\Livewire\Client\MissionLiveTracking;
use App\Livewire\Client\MultiTradesBundleManager;
use App\Livewire\Client\MyProtection;
use App\Livewire\Client\NpsSurvey;
use App\Livewire\Client\PlacesBook;
use App\Livewire\Client\ReceivedQuotes;
use App\Livewire\Client\ReferralProgramPage;
use App\Livewire\Client\RendezVousDetail;
use App\Livewire\Client\Templates\RecurringTemplatesGallery;
use App\Livewire\Client\WalletClient;
use App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard;
use App\Livewire\ClientDashboard;
use App\Livewire\Conversations\ConversationPage;
use App\Livewire\OrderEngine\OrderJourney;
use App\Livewire\Shared\ModulesDirectory;
use Illuminate\Support\Facades\Route;

$ecrans = function (bool $sousLEspaceSociete = false) {

    // L'accueil aussi : l'espace fusionne a le tableau de bord de la societe pour
    // maison, et deux accueils rendraient le doublon visible.
    if (! $sousLEspaceSociete) {
        Route::get('/', ClientDashboard::class)->name('dashboard');
    }

    // Sous l espace societe, le repertoire de la societe fait foi : deux annuaires
    // cote a cote redonneraient le sentiment de deux espaces.
    if (! $sousLEspaceSociete) {
        // Le répertoire des modules — voir `config/modules.php`. Le contexte vient de la route,
        // jamais de la requête : la garde reste `role:client`, ci-dessus.
        Route::get('/modules', ModulesDirectory::class)
            ->defaults('contexte', 'client')
            ->name('modules');
    }

    if (class_exists(MesRendezVousClient::class)) {
        Route::get('/rendez-vous', MesRendezVousClient::class)->name('rendezvous.index');
        // Récupération d'orphelin — gestion d'une série récurrente.
        Route::get('/rendez-vous/{rendezVous}/serie', EditRecurringBooking::class)->name('rendezvous.series');
    }

    /*
     * LE MÊME PARCOURS QUE `/commander`, monté ici.
     *
     * Un client connecté réservait par un formulaire, un visiteur par un autre : deux
     * questionnaires, deux calculs de prix, deux entrées de création de réservation — et donc
     * deux façons d'entrer dans le dispatch, dont une qui ignorait la zone et le métier.
     *
     * MONTÉ EN PLACE plutôt que redirigé : le tableau de bord garde sa navigation, son fil
     * d'Ariane et son adresse. Le composant sait déjà rattacher le panier au compte connecté
     * (`OrderDraftManager::resumeOrCreate` reçoit l'utilisateur), si bien que l'étape identité
     * est déjà satisfaite — le parcours PUBLIC reste prix-avant-identité, le parcours connecté
     * n'a simplement plus rien à demander.
     */
    Route::get('/rendez-vous/nouveau', OrderJourney::class)->name('rendezvous.create');

    /*
     * LA PAGE D'UN RENDEZ-VOUS — le suivi de mission et tout ce qui se gere.
     *
     * Declaree APRES `/rendez-vous/nouveau` et bornee aux chiffres : sans quoi elle
     * avalerait « nouveau ». Le suivi s'y monte, il ne vit plus sur chaque ligne.
     */
    Route::get('/rendez-vous/{booking}', RendezVousDetail::class)
        ->whereNumber('booking')
        ->name('rendezvous.show');

    if (class_exists(BrowseProviders::class)) {
        Route::get('/prestataires', BrowseProviders::class)->name('providers.browse');
    }

    /*
     * LES SOCIÉTÉS, ET PAS SEULEMENT LES PRESTATAIRES.
     *
     * `BrowseProviders` liste les intervenants par métier ; `BrowseCompanies` liste les
     * SOCIÉTÉS prestataires vérifiées. Le composant, sa vue et ses tests existaient depuis
     * le début — sans route ni appelant. Sur une place de marché où les deux côtés peuvent
     * être des sociétés, ce chemin manquait purement et simplement au client.
     */
    if (class_exists(BrowseCompanies::class)) {
        Route::get('/societes-prestataires', BrowseCompanies::class)->name('companies.browse');
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

    if (class_exists(HistoriqueClient::class)) {
        Route::get('/historique', HistoriqueClient::class)->name('historique');
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

    /*
     * LES DEVIS REÇUS (E24) — la moitié manquante du module.
     *
     * Une société qui bâtit un devis et l'envoie à un client qui ne peut pas y répondre n'a
     * rien envoyé : le client découvre le document par une notification et répond par
     * téléphone, c'est-à-dire hors de toute trace.
     */
    if (class_exists(ReceivedQuotes::class)) {
        Route::get('/devis', ReceivedQuotes::class)->name('quotes');
    }

    /*
     * LE CARNET DE LIEUX (E2). Un client a plusieurs lieux, et la plateforme n'en connaissait
     * qu'un : `customer_profiles` porte UNE adresse par défaut. Retaper l'adresse, l'étage et
     * le code à chaque commande fait se tromper une fois sur cinq — et envoie un
     * professionnel à la mauvaise porte.
     */
    if (class_exists(PlacesBook::class)) {
        Route::get('/mes-lieux', PlacesBook::class)->name('places');
    }

    /*
     * LE BUDGET MAISON (E4). Tout est déjà en base et personne ne le voit : un client reçoit
     * ses factures une par une, sans moyen de répondre à la seule question qu'il se pose —
     * « combien est-ce que je dépense, et est-ce que ça augmente ».
     */
    /*
     * « MA PROTECTION » (E6). Insurance, Cancellation v2 et Disputes existent, chacun avec
     * son écran — et aucun client ne sait ce qu'il a. Il découvre son assurance au moment du
     * sinistre, ses frais d'annulation en annulant. Une protection qu'on ne peut pas énoncer
     * AVANT d'en avoir besoin n'en est pas une.
     */
    if (class_exists(MyProtection::class)) {
        Route::get('/ma-protection', MyProtection::class)->name('protection');
    }

    if (class_exists(ClientTipBooking::class)) {
        Route::get('/missions/{bookingId}/pourboire', ClientTipBooking::class)
            ->name('tip.booking');
    }

    if (class_exists(BookingCheckout::class)) {
        Route::get('/missions/{bookingId}/checkout', BookingCheckout::class)
            ->name('booking.checkout');
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
};

Route::middleware(['role:client', 'espace.fusionne'])
    ->prefix('dashboard/client')
    ->name('client.')
    ->group(fn () => $ecrans());

/*
| LES MEMES ECRANS, SOUS L'ESPACE SOCIETE.
|
| Un compte rattache a une societe n'a plus deux espaces : ses ecrans personnels vivent
| sous `/dashboard/entreprise-client/moi`, avec la barre et le repertoire de la societe.
| Le corps est LE MEME : re-declarer 43 routes a la main aurait perdu leurs gardes.
*/
Route::middleware(['role:client', 'org.type:client'])
    ->prefix('dashboard/entreprise-client/moi')
    ->name('client-company.perso.')
    ->group(fn () => $ecrans(true));
