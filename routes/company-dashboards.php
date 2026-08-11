<?php

use App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard;
use App\Livewire\ClientCompany\BillingCenter;
use App\Livewire\ClientCompany\BookingHub;
use App\Livewire\ClientCompany\BulkBookingImporter;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Livewire\ClientCompany\ClientContractsCenter;
use App\Livewire\ClientCompany\DisputesCenter;
use App\Livewire\ClientCompany\MembersAccess;
use App\Livewire\ClientCompany\MultiSiteRequest;
use App\Livewire\ClientCompany\SigningAppointments;
use App\Livewire\ClientCompany\SiteManager;
use App\Livewire\ClientCompany\SiteMissionPhotos;
use App\Livewire\ProviderCompany\Agencies;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Livewire\ProviderCompany\FieldTeams;
use App\Livewire\ProviderCompany\InventoryCenter;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Livewire\ProviderCompany\QualityAndFleet;
use App\Livewire\ProviderCompany\QuoteBuilder;
use App\Livewire\ProviderCompany\RecruitmentCenter;
use App\Livewire\ProviderCompany\RolePermissionsMatrix;
use App\Livewire\ProviderCompany\SiteOperations;
use App\Livewire\ProviderCompany\TaskBoard;
use App\Livewire\ProviderCompany\TeamChannels;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Livewire\ProviderCompany\TimesheetCenter;
use App\Livewire\ProviderCompany\WorkforcePlanning;
use App\Livewire\Shared\ModulesDirectory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes — Entreprise cliente (client_company)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'active.account', 'org.type:client'])
    ->prefix('dashboard/entreprise-client')
    ->name('client-company.')
    ->group(function () {

        Route::get('/', ClientCompanyDashboard::class)->name('dashboard');

        // Le répertoire des modules — voir `config/modules.php`. La garde d'organisation
        // (`org.type:client`) reste celle du groupe.
        Route::get('/modules', ModulesDirectory::class)
            ->defaults('contexte', 'client-company')
            ->name('modules');

        Route::get('/locaux', SiteManager::class)->name('sites');
        Route::get('/reservations', BookingHub::class)->name('bookings.index');
        Route::get('/reservations/nouveau', BookingHub::class)->name('bookings.create');
        Route::get('/reservations/multi-locaux', MultiSiteRequest::class)->name('bookings.multi-site');
        Route::get('/membres', MembersAccess::class)->name('members');
        Route::get('/contrats', ClientContractsCenter::class)->name('contracts');
        Route::get('/contrats/signatures-sur-place', SigningAppointments::class)->name('contracts.signing-appointments');
        Route::get('/facturation', BillingCenter::class)->name('billing');

        if (class_exists(ClientAnalyticsDashboard::class)) {
            Route::get('/analytics', ClientAnalyticsDashboard::class)->name('analytics');
        }

        if (class_exists(DisputesCenter::class)) {
            Route::get('/litiges', DisputesCenter::class)->name('disputes');
        }

        if (class_exists(SiteMissionPhotos::class)) {
            Route::get('/missions/{mission}/photos', SiteMissionPhotos::class)->name('missions.photos');
        }

        if (class_exists(BulkBookingImporter::class)) {
            Route::get('/reservations/import-bulk', BulkBookingImporter::class)
                ->name('bookings.bulk-import');
        }
    });

/*
|--------------------------------------------------------------------------
| Routes — Entreprise prestataire (provider_company)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'active.account', 'org.type:provider'])
    ->prefix('dashboard/entreprise-prestataire')
    ->name('provider-company.')
    ->group(function () {

        Route::get('/', ProviderDashboard::class)->name('dashboard');

        // Le répertoire des modules — voir `config/modules.php`. La garde d'organisation
        // (`org.type:provider`) reste celle du groupe.
        Route::get('/modules', ModulesDirectory::class)
            ->defaults('contexte', 'provider-company')
            ->name('modules');

        Route::get('/canaux', TeamChannels::class)->name('channels');
        Route::get('/taches', TaskBoard::class)->name('tasks');
        Route::get('/dispatch', DispatchCenter::class)->name('dispatch');
        Route::get('/equipe', TeamManagement::class)->name('team');
        /*
         * « Chez nous, les chefs d'équipe assignent les missions. » `organization_role_permissions`
         * était lue par `PermissionService` et écrite par personne : la règle de maison réclamait un
         * déploiement. Cet écran est son premier écrivain.
         */
        Route::get('/roles-permissions', RolePermissionsMatrix::class)->name('role-permissions');
        Route::get('/equipes-terrain', FieldTeams::class)->name('field-teams');
        /*
         * Les IMPLANTATIONS de la société — ses dépôts, ses antennes. L'API et l'écran natif
         * existaient depuis le lot société ; le web, non. Une société qui pilote depuis un
         * ordinateur ne pouvait pas déclarer d'où partent ses équipes.
         */
        Route::get('/implantations', Agencies::class)->name('agencies');
        // Les sites clients desservis, et le référent que la société y place.
        Route::get('/sites', SiteOperations::class)->name('sites');

        /*
         * QUI TRAVAILLE QUAND (E19) et qui s'absente (E21) — sur le même écran parce que c'est la
         * même question. Les séparer produirait un planning qui ignore les congés, et des congés qui
         * ne bloquent rien.
         */
        Route::get('/planning', WorkforcePlanning::class)->name('planning');

        // Les heures pointées (E20) et ce qu'elles coûtent (E22) : la marge est le produit moins
        // les heures et les consommables, on ne peut pas montrer l'une sans l'autre.
        Route::get('/heures', TimesheetCenter::class)->name('timesheets');

        // Le stock de consommables (E23), alimenté aussi depuis le terrain (F7).
        Route::get('/consommables', InventoryCenter::class)->name('inventory');

        /*
         * LE DEVIS QUE LA SOCIÉTÉ BÂTIT ELLE-MÊME (E24). « Je passe voir, je chiffre, je vous
         * envoie ça » n'existait que dans l'écran d'administration de la PLATEFORME : un opérateur
         * tapait le prix à la main pour le compte de la société.
         */
        Route::get('/devis', QuoteBuilder::class)->name('quotes');

        // Le recrutement (E25). L'invitation à jeton concluait déjà ; l'offre et les candidatures
        // se passaient hors de la plateforme.
        Route::get('/recrutement', RecruitmentCenter::class)->name('recruitment');

        // Le score qualité interne (E26) et la flotte de la société (E27) — même question : qui
        // peut travailler demain, et avec quoi.
        Route::get('/qualite-materiel', QualityAndFleet::class)->name('quality-fleet');
    });
