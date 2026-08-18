<?php

use App\Livewire\Admin\ActivityLogsCenter;
use App\Livewire\Admin\AdminAutomationCenter;
use App\Livewire\Admin\AdminB2BOperationsCenter;
use App\Livewire\Admin\AdminCalendar;
use App\Livewire\Admin\AdminEmailsCenter;
use App\Livewire\Admin\AdminFinance;
use App\Livewire\Admin\AdminInternationalOperationsCenter;
use App\Livewire\Admin\AdminOrchestrationTerrainCenter;
use App\Livewire\Admin\AdminPlanning;
use App\Livewire\Admin\AdminPremiumClients;
use App\Livewire\Admin\AdminTeamsPartnersCenter;
use App\Livewire\Admin\AdminTools;
use App\Livewire\Admin\AuditLogs;
use App\Livewire\Admin\AuditLogsCenter;
use App\Livewire\Admin\AutomationCenter;
use App\Livewire\Admin\B2BOperationsCenter;
use App\Livewire\Admin\CalendrierInterne;
use App\Livewire\Admin\CatalogueServices;
use App\Livewire\Admin\CountryOperationsCenter;
use App\Livewire\Admin\EmailsCenter;
use App\Livewire\Admin\ExportTools;
use App\Livewire\Admin\FeedbacksAdmin;
use App\Livewire\Admin\FinanceCenter;
use App\Livewire\Admin\FinanceDashboard;
use App\Livewire\Admin\GestionEquipesPartenaires;
use App\Livewire\Admin\InternationalOperationsCenter;
use App\Livewire\Admin\ModulesCenter;
use App\Livewire\Admin\OrchestrationTerrainCenter;
use App\Livewire\Admin\OutilsAdmin;
use App\Livewire\Admin\PlanningAdmin;
use App\Livewire\Admin\PlatformModulesCenter;
use App\Livewire\Admin\PremiumClients;
use App\Livewire\Admin\PremiumClientsManager;
use App\Livewire\Admin\ProductEmailsCenter;
use App\Livewire\Admin\TeamsPartnersCenter;
use App\Livewire\AdminFeedbacks;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Advanced missing route fixes
|--------------------------------------------------------------------------
| This file registers advanced pages detected by route-audit.php.
| It avoids "Route [...] not defined" while keeping the app stable when
| a Livewire component does not exist yet.
*/

$fallbackPage = function (string $title, ?string $message = null) {
    /*
     * LE REPLI PASSE PAR LE GABARIT, comme toutes les autres pages.
     *
     * Il rendait un document HTML complet écrit à la main ici même — avec son propre `<head>` et
     * Tailwind chargé depuis un CDN. Il échappait donc à la barre de navigation, au thème sombre,
     * à la marque, et à la règle du dépôt qui veut que les assets soient servis par Vite. Un
     * balayage de marque page par page l'a trouvé : c'était le seul écran authentifié à ne porter
     * aucune identité.
     */
    return fn () => response()->view('admin.module-a-connecter', [
        'titre' => $title,
        'message' => $message,
    ]);
};

$livewireOrFallback = function (array $classes, string $title) use ($fallbackPage) {
    foreach ($classes as $class) {
        if (class_exists($class)) {
            return $class;
        }
    }

    return $fallbackPage($title);
};

Route::middleware(['auth', 'verified', 'active.account'])->group(function () use ($livewireOrFallback) {

    /*
    |--------------------------------------------------------------------------
    | Admin advanced pages
    |--------------------------------------------------------------------------
    */

    // `module_gate` : meme porte que le groupe principal de `routes/admin.php`. Ce fichier sert des
    // modules CATALOGUES -- `admin.finance` en est un -- et l'oublier ici laissait leur ecran ouvert
    // pendant que la navigation cachait leur tuile : une porte invisible mais deverrouillee, c'est-a-dire
    // l'inverse exact du defaut qu'on corrige. L'intermediaire est sans effet sur une route qui ne
    // declare aucune capacite.
    Route::middleware(['role:admin', 'enforce_2fa', 'module_gate'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () use ($livewireOrFallback) {

            if (! Route::has('admin.planning')) {
                Route::get('/planning', $livewireOrFallback([
                    PlanningAdmin::class,
                    CalendrierInterne::class,
                    AdminPlanning::class,
                ], 'Planning admin'))->name('planning');
            }

            if (! Route::has('admin.calendar')) {
                Route::get('/calendar', $livewireOrFallback([
                    CalendrierInterne::class,
                    PlanningAdmin::class,
                    AdminCalendar::class,
                ], 'Calendrier admin'))->name('calendar');
            }

            if (! Route::has('admin.feedbacks')) {
                Route::get('/feedbacks', $livewireOrFallback([
                    AdminFeedbacks::class,
                    AdminFeedbacks::class,
                    FeedbacksAdmin::class,
                ], 'Feedbacks admin'))->name('feedbacks');
            }

            if (! Route::has('admin.feedbacks.export')) {
                Route::get('/feedbacks/export', function () {
                    if (class_exists(Pdf::class)) {
                        return Pdf::loadHTML('<h1>Export feedbacks</h1>')
                            ->download('feedbacks.pdf');
                    }

                    return response('<h1>Export feedbacks</h1>', 200);
                });
            }

            if (! Route::has('admin.finance')) {
                Route::get('/finance', $livewireOrFallback([
                    FinanceDashboard::class,
                    FinanceCenter::class,
                    AdminFinance::class,
                ], 'Finance admin'))->name('finance');
            }

            if (! Route::has('admin.outils')) {
                Route::get('/outils', $livewireOrFallback([
                    OutilsAdmin::class,
                    AdminTools::class,
                    ExportTools::class,
                ], 'Outils admin'))->name('outils');
            }

            if (! Route::has('admin.audit.logs')) {
                Route::get('/audit/logs', $livewireOrFallback([
                    AuditLogsCenter::class,
                    AuditLogs::class,
                    ActivityLogsCenter::class,
                ], 'Audit logs'))->name('audit.logs');
            }

            Route::get('/services', CatalogueServices::class)
                ->middleware('can:manage-services')
                ->name('services');

            if (! Route::has('admin.premium.clients')) {
                Route::get('/premium/clients', $livewireOrFallback([
                    PremiumClients::class,
                    PremiumClientsManager::class,
                    AdminPremiumClients::class,
                ], 'Clients premium'))->name('premium.clients');
            }

            if (! Route::has('admin.b2b.operations')) {
                Route::get('/b2b/operations', $livewireOrFallback([
                    B2BOperationsCenter::class,
                    AdminB2BOperationsCenter::class,
                ], 'Opérations B2B'))->name('b2b.operations');
            }

            if (! Route::has('admin.teams.partners')) {
                Route::get('/teams-partners', $livewireOrFallback([
                    GestionEquipesPartenaires::class,
                    TeamsPartnersCenter::class,
                    AdminTeamsPartnersCenter::class,
                ], 'Équipes terrain & partenaires'))
                    ->middleware('can:manage-entreprises')
                    ->name('teams.partners');
            }

            if (! Route::has('admin.international')) {
                Route::get('/international', $livewireOrFallback([
                    InternationalOperationsCenter::class,
                    AdminInternationalOperationsCenter::class,
                ], 'Opérations internationales'))->name('international');
            }

            if (! Route::has('admin.orchestration')) {
                Route::get('/orchestration', $livewireOrFallback([
                    OrchestrationTerrainCenter::class,
                    AdminOrchestrationTerrainCenter::class,
                ], 'Orchestration terrain'))->name('orchestration');
            }

            if (! Route::has('admin.automation')) {
                Route::get('/automation', $livewireOrFallback([
                    AutomationCenter::class,
                    AdminAutomationCenter::class,
                ], 'Automatisation'))->name('automation');
            }

            if (! Route::has('admin.modules')) {
                Route::get('/modules', $livewireOrFallback([
                    PlatformModulesCenter::class,
                    ModulesCenter::class,
                ], 'Modules plateforme'))
                    ->middleware('can:manage-modules')
                    ->name('modules');
            }

            if (! Route::has('admin.countries')) {
                Route::get('/countries', CountryOperationsCenter::class)
                    ->name('countries');
            }

            if (! Route::has('admin.emails')) {
                Route::get('/emails', $livewireOrFallback([
                    ProductEmailsCenter::class,
                    EmailsCenter::class,
                    AdminEmailsCenter::class,
                ], 'Centre e-mails'))->name('emails');
            }

            if (! Route::has('admin.export.pdf')) {
                Route::get('/export/pdf', function () {
                    if (class_exists(Pdf::class)) {
                        $html = '
                            <h1>Export global Brio</h1>
                            <p>Export PDF temporaire. À remplacer par la logique ExportTools.</p>
                        ';

                        return Pdf::loadHTML($html)
                            ->download('brio-export-global.pdf');
                    }

                    return response('Export PDF global à implémenter.', 200);
                })->name('export.pdf');
            }

            if (! Route::has('admin.feedbacks.export')) {
                Route::get('/feedbacks/export', function () {
                    $user = auth()->user();

                    abort_unless($user && $user->isAdmin(), 403);

                    if (class_exists(Pdf::class)) {
                        return Pdf::loadHTML('
                        <h1>Export feedbacks</h1>
                        <p>Export PDF temporaire des feedbacks.</p>
                    ')->download('feedbacks.pdf');
                    }

                    return response('<h1>Export feedbacks</h1>', 200);
                })->name('feedbacks.export');
            }

            if (! Route::has('admin.rendezvous.series.edit')) {
                Route::get('/rendez-vous-series/{series}/edit', function ($series) {
                    return response(
                        '<h1>Gérer une série récurrente</h1><p>Série ID : '.e($series).'</p>',
                        200
                    );
                })->name('rendezvous.series.edit');
            }

            if (! Route::has('admin.export.csv')) {
                Route::get('/export/csv', function () {
                    $user = auth()->user();

                    abort_unless($user && $user->canPerformCriticalAdminActions(), 403);

                    $query = Booking::query()->with('serviceZone');

                    if ($user->isZoneScopedAdmin()) {
                        $query->where('service_zone_id', $user->managed_service_zone_id);
                    }

                    return response()->streamDownload(function () use ($query) {
                        echo "id,service_zone,status,date\n";

                        $query->chunk(100, function ($rows) {
                            foreach ($rows as $rdv) {
                                echo implode(',', [
                                    $rdv->id,
                                    '"'.str_replace('"', '""', (string) ($rdv->serviceZone?->name ?? '')).'"',
                                    $rdv->status,
                                    $rdv->date,
                                ])."\n";
                            }
                        });
                    }, 'rendez-vous-export.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                })->name('export.csv');
            }

            /*
             * `/admin/premium-clients` (nom `admin.premium.clients.legacy`) a été retiré le
             * 2026-08-05 : il servait le MÊME composant que `admin.premium.clients`
             * (`/admin/premium/clients`) sous une ancienne URI. Un composant, une adresse ;
             * les anciens liens vers `/admin/premium-clients` répondent désormais 404.
             */
        });

    /*
    |--------------------------------------------------------------------------
    | Client advanced pages
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:client'])
        ->prefix('dashboard/client')
        ->name('client.')
        ->group(function () {

            if (! Route::has('client.rendezvous.series.edit')) {
                Route::get('/rendez-vous-series/{series}/edit', function ($series) {
                    return response(
                        '<h1>Gérer ma série récurrente</h1><p>Série ID : '.e($series).'</p>',
                        200
                    );
                })->name('rendezvous.series.edit');
            }
        });
});
