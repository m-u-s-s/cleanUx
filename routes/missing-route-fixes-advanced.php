<?php

use App\Livewire\Admin\AuditLogsCenter;
use App\Livewire\Admin\CalendrierInterne;
use App\Livewire\Admin\CountryOperationsCenter;
use App\Livewire\Admin\ExportTools;
use App\Livewire\Admin\FinanceCenter;
use App\Livewire\Admin\OutilsAdmin;
use App\Livewire\Admin\PlanningAdmin;
use App\Livewire\Admin\PlatformModulesCenter;
use App\Livewire\Admin\ProductEmailsCenter;
use App\Livewire\AdminFeedbacks;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Advanced missing route fixes
|--------------------------------------------------------------------------
| This file registers advanced pages detected by route-audit.php.
| It avoids "Route [...] not defined" while keeping the app stable when
| a Livewire component does not exist yet.
*/

$fallbackPage = function (string $title, string $message = null) {
    return function () use ($title, $message) {
        return response(
            '<!DOCTYPE html>
            <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . e($title) . '</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="min-h-screen bg-slate-50 text-slate-900">
                <main class="mx-auto max-w-4xl px-6 py-12">
                    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600">CleanUx</p>
                        <h1 class="mt-3 text-3xl font-black">' . e($title) . '</h1>
                        <p class="mt-4 text-slate-600">' . e($message ?: 'Cette page est maintenant routée. Il reste à connecter le vrai composant ou la vraie logique métier.') . '</p>
                        <a href="' . e(route('dashboard')) . '" class="mt-6 inline-flex rounded-2xl bg-blue-600 px-5 py-3 text-sm font-bold text-white hover:bg-blue-700">
                            Retour dashboard
                        </a>
                    </div>
                </main>
            </body>
            </html>'
        );
    };
};

$livewireOrFallback = function (array $classes, string $title) use ($fallbackPage) {
    foreach ($classes as $class) {
        if (class_exists($class)) {
            return $class;
        }
    }

    return $fallbackPage($title);
};

Route::middleware(['auth', 'verified', 'active.account'])->group(function () use ($fallbackPage, $livewireOrFallback) {

    /*
    |--------------------------------------------------------------------------
    | Admin advanced pages
    |--------------------------------------------------------------------------
    */

    Route::middleware(['role:admin'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function () use ($fallbackPage, $livewireOrFallback) {

            if (! Route::has('admin.planning')) {
                Route::get('/planning', $livewireOrFallback([
                    PlanningAdmin::class,
                    CalendrierInterne::class,
                    \App\Livewire\Admin\AdminPlanning::class,
                ], 'Planning admin'))->name('planning');
            }

            if (! Route::has('admin.calendar')) {
                Route::get('/calendar', $livewireOrFallback([
                    CalendrierInterne::class,
                    PlanningAdmin::class,
                    \App\Livewire\Admin\AdminCalendar::class,
                ], 'Calendrier admin'))->name('calendar');
            }

            if (! Route::has('admin.feedbacks')) {
                Route::get('/feedbacks', $livewireOrFallback([
                    AdminFeedbacks::class,
                    AdminFeedbacks::class,
                    \App\Livewire\Admin\FeedbacksAdmin::class,
                ], 'Feedbacks admin'))->name('feedbacks');
            }

            if (! Route::has('admin.feedbacks.export')) {
                Route::get('/feedbacks/export', function () {
                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Export feedbacks</h1>')
                            ->download('feedbacks.pdf');
                    }

                    return response('<h1>Export feedbacks</h1>', 200);
                });
            }

            if (! Route::has('admin.feedbacks.export.csv')) {
                Route::get('/feedbacks/export/csv', function () {
                    $user = auth()->user();

                    abort_unless($user && $user->isAdmin(), 403);

                    $query = \App\Models\Feedback::query()
                        ->with('rendezVous.serviceZone');

                    if ($user->isZoneScopedAdmin()) {
                        $query->whereHas('rendezVous', function ($q) use ($user) {
                            $q->where('service_zone_id', $user->managed_service_zone_id);
                        });
                    }

                    $callback = function () use ($query) {
                        echo "id\n";

                        $query->chunk(100, function ($rows) {
                            foreach ($rows as $feedback) {
                                echo $feedback->id . "\n";
                            }
                        });
                    };

                    return new class($callback, 200, ['Content-Type' => 'text/csv']) extends \Symfony\Component\HttpFoundation\StreamedResponse {
                        public function prepare(\Symfony\Component\HttpFoundation\Request $request): static
                        {
                            parent::prepare($request);

                            $this->headers->set('Content-Type', 'text/csv', true);

                            return $this;
                        }
                    };
                })->name('feedbacks.export.csv');
            }

            if (! Route::has('admin.finance')) {
                Route::get('/finance', $livewireOrFallback([
                    \App\Livewire\Admin\FinanceDashboard::class,
                    FinanceCenter::class,
                    \App\Livewire\Admin\AdminFinance::class,
                ], 'Finance admin'))->name('finance');
            }

            if (! Route::has('admin.outils')) {
                Route::get('/outils', $livewireOrFallback([
                    OutilsAdmin::class,
                    \App\Livewire\Admin\AdminTools::class,
                    ExportTools::class,
                ], 'Outils admin'))->name('outils');
            }

            if (! Route::has('admin.audit.logs')) {
                Route::get('/audit/logs', $livewireOrFallback([
                    AuditLogsCenter::class,
                    \App\Livewire\Admin\AuditLogs::class,
                    \App\Livewire\Admin\ActivityLogsCenter::class,
                ], 'Audit logs'))->name('audit.logs');
            }

            if (! Route::has('admin.services')) {
                Route::get('/services', $livewireOrFallback([
                    \App\Livewire\Admin\ServicesAdmin::class,
                    \App\Livewire\Admin\ServiceCatalogManager::class,
                    \App\Livewire\Admin\ServicesManager::class,
                ], 'Services admin'))
                    ->middleware('can:manage-services')
                    ->name('services');
            }

            if (! Route::has('admin.premium.clients')) {
                Route::get('/premium/clients', $livewireOrFallback([
                    \App\Livewire\Admin\PremiumClients::class,
                    \App\Livewire\Admin\PremiumClientsManager::class,
                    \App\Livewire\Admin\AdminPremiumClients::class,
                ], 'Clients premium'))->name('premium.clients');
            }

            if (! Route::has('admin.b2b.operations')) {
                Route::get('/b2b/operations', $livewireOrFallback([
                    \App\Livewire\Admin\B2BOperationsCenter::class,
                    \App\Livewire\Admin\AdminB2BOperationsCenter::class,
                ], 'Opérations B2B'))->name('b2b.operations');
            }

            if (! Route::has('admin.teams.partners')) {
                Route::get('/teams-partners', $livewireOrFallback([
                    \App\Livewire\Admin\GestionEquipesPartenaires::class,
                    \App\Livewire\Admin\TeamsPartnersCenter::class,
                    \App\Livewire\Admin\AdminTeamsPartnersCenter::class,
                ], 'Équipes terrain & partenaires'))
                    ->middleware('can:manage-entreprises')
                    ->name('teams.partners');
            }

            if (! Route::has('admin.international')) {
                Route::get('/international', $livewireOrFallback([
                    \App\Livewire\Admin\InternationalOperationsCenter::class,
                    \App\Livewire\Admin\AdminInternationalOperationsCenter::class,
                ], 'Opérations internationales'))->name('international');
            }

            if (! Route::has('admin.orchestration')) {
                Route::get('/orchestration', $livewireOrFallback([
                    \App\Livewire\Admin\OrchestrationTerrainCenter::class,
                    \App\Livewire\Admin\AdminOrchestrationTerrainCenter::class,
                ], 'Orchestration terrain'))->name('orchestration');
            }

            if (! Route::has('admin.automation')) {
                Route::get('/automation', $livewireOrFallback([
                    \App\Livewire\Admin\AutomationCenter::class,
                    \App\Livewire\Admin\AdminAutomationCenter::class,
                ], 'Automatisation'))->name('automation');
            }

            if (! Route::has('admin.modules')) {
                Route::get('/modules', $livewireOrFallback([
                    PlatformModulesCenter::class,
                    \App\Livewire\Admin\ModulesCenter::class,
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
                    \App\Livewire\Admin\EmailsCenter::class,
                    \App\Livewire\Admin\AdminEmailsCenter::class,
                ], 'Centre e-mails'))->name('emails');
            }

            if (! Route::has('admin.export.pdf')) {
                Route::get('/export/pdf', function () {
                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        $html = '
                            <h1>Export global CleanUx</h1>
                            <p>Export PDF temporaire. À remplacer par la logique ExportTools.</p>
                        ';

                        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                            ->download('cleanux-export-global.pdf');
                    }

                    return response('Export PDF global à implémenter.', 200);
                })->name('export.pdf');
            }

            if (! Route::has('admin.feedbacks.export')) {
                Route::get('/feedbacks/export', function () {
                    $user = auth()->user();

                    abort_unless($user && $user->isAdmin(), 403);

                    if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                        return \Barryvdh\DomPDF\Facade\Pdf::loadHTML('
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
                        '<h1>Gérer une série récurrente</h1><p>Série ID : ' . e($series) . '</p>',
                        200
                    );
                })->name('rendezvous.series.edit');
            }

            Route::get('/export/csv', function () {
                $user = auth()->user();

                abort_unless($user && $user->canPerformCriticalAdminActions(), 403);

                $query = \App\Models\RendezVous::query()->with('serviceZone');

                if ($user->isZoneScopedAdmin()) {
                    $query->where('service_zone_id', $user->managed_service_zone_id);
                }

                return response()->streamDownload(function () use ($query) {
                    echo "id,service_zone,status,date\n";

                    $query->chunk(100, function ($rows) {
                        foreach ($rows as $rdv) {
                            echo implode(',', [
                                $rdv->id,
                                '"' . str_replace('"', '""', (string) ($rdv->serviceZone?->name ?? '')) . '"',
                                $rdv->status,
                                $rdv->date,
                            ]) . "\n";
                        }
                    });
                }, 'rendez-vous-export.csv', [
                    'Content-Type' => 'text/csv',
                ]);
            });


            Route::get('/feedbacks/export-csv', function () {
                $user = auth()->user();

                abort_unless($user && $user->canPerformCriticalAdminActions(), 403);

                $query = \App\Models\Feedback::query()
                    ->with('rendezVous.serviceZone');

                if ($user->isZoneScopedAdmin()) {
                    $query->whereHas('rendezVous', function ($q) use ($user) {
                        $q->where('service_zone_id', $user->managed_service_zone_id);
                    });
                }

                $rows = $query->get();

                $csv = "id,rendez_vous_id,commentaire\n";

                foreach ($rows as $feedback) {
                    $csv .= implode(',', [
                        $feedback->id,
                        $feedback->rendez_vous_id,
                        '"' . str_replace('"', '""', (string) ($feedback->commentaire ?? $feedback->comment ?? '')) . '"',
                    ]) . "\n";
                }

                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                ]);
            });


            Route::get('/premium-clients', function () {
                return redirect()->route('admin.premium.clients');
            });

            Route::get('/utilisateurs', function () {
                if (Route::has('admin.utilisateurs.manage')) {
                    return redirect()->route('admin.utilisateurs.manage');
                }

                abort(404);
            });


            Route::get('/utilisateurs', function () {
                if (class_exists(\App\Livewire\Admin\UtilisateursAdmin::class)) {
                    return app(\Livewire\LivewireManager::class)
                        ? \Illuminate\Support\Facades\Blade::render('@livewire(\App\Livewire\Admin\UtilisateursAdmin::class)')
                        : response('<h1>Gestion utilisateurs</h1>', 200);
                }

                return response('<h1>Gestion utilisateurs</h1>', 200);
            });

            Route::get('/premium-clients', function () {
                if (class_exists(\App\Livewire\Admin\PremiumClientsManager::class)) {
                    return app(\Livewire\LivewireManager::class)
                        ? \Illuminate\Support\Facades\Blade::render('@livewire(\App\Livewire\Admin\PremiumClientsManager::class)')
                        : response('<h1>Clients premium</h1>', 200);
                }

                return response('<h1>Clients premium</h1>', 200);
            });
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
                        '<h1>Gérer ma série récurrente</h1><p>Série ID : ' . e($series) . '</p>',
                        200
                    );
                })->name('rendezvous.series.edit');
            }
        });
});
