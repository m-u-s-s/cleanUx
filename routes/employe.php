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
use App\Livewire\Provider\BundleQuoteRequests;
use App\Livewire\Provider\DemandHeatmap;
use App\Livewire\Provider\ProviderBadgesPage;
use App\Livewire\Provider\ProviderDisputesPage;
use App\Livewire\Provider\ProviderDrivingDossier;
use App\Livewire\Provider\ProviderEarningsDashboard;
use App\Livewire\Provider\ProviderKycPage;
use App\Livewire\Provider\ProviderRatingsPage;
use App\Livewire\Provider\ProviderWalletPage;
use App\Livewire\Provider\SafetyPanel;
use App\Livewire\Provider\TradesAndZones;
use App\Livewire\Shared\ModulesDirectory;
use Illuminate\Support\Facades\Route;

/*
| L'ATTENTE D'APPROBATION VAUT AUSSI DANS LE NAVIGATEUR.
|
| `provider.approved` ne gardait que les routes d'API : la même personne était bloquée dans
| l'application et servie ici (mesuré le 2026-08-16), alors que le formulaire d'inscription promet
| « Votre compte sera vérifié par notre équipe avant d'être activé ». Le middleware ne vise que les
| comptes portant `self_registered_at` — les prestataires antérieurs traversent sans condition.
|
| CINQ ROUTES S'EN EXCLUENT, et la liste n'est pas arbitraire : ce sont celles par lesquelles on
| COMPLÈTE le dossier. Les garder sous la garde enfermerait le compte dehors — il lui faudrait une
| approbation pour fournir ce qui permet de l'approuver. Le tableau de bord reste ouvert parce
| qu'il est la page d'atterrissage après connexion et qu'il porte l'avancement du dossier ; un 403
| là serait la première chose que voit un nouveau prestataire.
*/
Route::middleware(['role:employe', 'provider.approved'])
    ->prefix('dashboard/employe')
    ->name('employe.')
    ->group(function () {

        Route::get('/', EmployeDashboard::class)
            ->withoutMiddleware('provider.approved')
            ->name('dashboard');

        /*
         * « CE QUE JE FAIS, ET OÙ » — l'écran qui décide de ce qu'un prestataire reçoit.
         *
         * Il n'existait pas : le métier se déclarait une fois à l'inscription et ne se modifiait
         * plus, les zones ne se déclaraient nulle part. Un prestataire qui déménageait devait
         * écrire au support et attendre qu'un administrateur touche la base.
         */
        Route::get('/metiers-zones', TradesAndZones::class)
            ->withoutMiddleware('provider.approved')
            ->name('trades-zones');

        // Le répertoire des modules — voir `config/modules.php`. La garde reste `role:employe`.
        Route::get('/modules', ModulesDirectory::class)
            ->defaults('contexte', 'employe')
            ->name('modules');

        if (class_exists(ProviderRatingsPage::class)) {
            Route::get('/avis', ProviderRatingsPage::class)->name('ratings');
        }

        if (class_exists(ProviderWalletPage::class)) {
            Route::get('/portefeuille', ProviderWalletPage::class)->name('wallet');
        }

        if (class_exists(ProviderDisputesPage::class)) {
            Route::get('/litiges', ProviderDisputesPage::class)->name('disputes');
        }

        if (class_exists(BundleQuoteRequests::class)) {
            Route::get('/devis-chantiers', BundleQuoteRequests::class)->name('bundle-quotes');
        }

        if (class_exists(ProviderKycPage::class)) {
            Route::get('/verification', ProviderKycPage::class)
                ->withoutMiddleware('provider.approved')
                ->name('kyc');
        }

        /*
         * Le dossier de CONDUITE — permis, assurance du véhicule, carte grise, et la voiture.
         *
         * Distinct de `/verification`, qui traite l'identité : ce sont deux questions différentes,
         * et les mêler ferait chercher un permis dans un écran qui parle de pièce d'identité.
         */
        if (class_exists(ProviderDrivingDossier::class)) {
            Route::get('/conduite', ProviderDrivingDossier::class)
                ->withoutMiddleware('provider.approved')
                ->name('driving');
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

        /*
         * LE MODE SÉCURITÉ / SOS (E33), CÔTÉ WEB.
         *
         * Le terrain est mobile, mais tout le monde n'a pas installé l'application : un
         * indépendant qui travaille depuis son navigateur, quelqu'un dont le téléphone est
         * déchargé. Réserver le bouton d'urgence à une surface, c'est le refuser à qui n'y est pas.
         *
         * PAS DE `class_exists` ICI, contrairement à ses voisines : une route d'urgence qui
         * disparaît silencieusement quand une classe manque est exactement ce qu'on ne veut pas.
         * Si elle casse, on veut le savoir au déploiement.
         */
        Route::get('/securite', SafetyPanel::class)->name('safety');

        /*
         * OÙ ME PLACER, ET À QUELLE HEURE (E12).
         *
         * La question que se pose tout indépendant le matin, et à laquelle rien ne répondait : il
         * se place où il s'est placé hier, et découvre après trois heures d'attente qu'il fallait
         * être ailleurs. Les recherches de dispatch sont horodatées et géolocalisées depuis le
         * chantier de répartition — c'est cette donnée qu'on lui rend.
         */
        Route::get('/demande', DemandHeatmap::class)->name('heatmap');

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

        /*
         * L'ouverture du compte de paiement fait PARTIE du dossier : sans compte Connect actif, la
         * plateforme refuse de prélever un client faute de pouvoir reverser. Ces trois routes
         * s'excluent donc de l'attente d'approbation, comme la vérification d'identité.
         */
        if (class_exists(StripeConnectController::class)) {
            Route::withoutMiddleware('provider.approved')->group(function () {
                Route::get('/stripe-connect/start', [StripeConnectController::class, 'start'])
                    ->name('stripe-connect.start');

                Route::get('/stripe-connect/refresh', [StripeConnectController::class, 'refresh'])
                    ->name('stripe-connect.refresh');

                Route::get('/stripe-connect/return', [StripeConnectController::class, 'return'])
                    ->name('stripe-connect.return');
            });
        }

        if (class_exists(FeedbacksEmploye::class)) {
            Route::get('/feedbacks', FeedbacksEmploye::class)->name('feedbacks');
        }

        if (class_exists(ValidationMultipleRdv::class)) {
            Route::get('/validation-multiple-rdv', ValidationMultipleRdv::class)
                ->name('validation.multiple');
        }
    });
