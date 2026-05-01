<?php

use App\Http\Controllers\Admin\MissionAdminController;
use App\Livewire\Admin\GestionUtilisateurs;
use App\Livewire\Admin\OutilsAdmin;
use App\Livewire\Admin\PlanningAdmin;
use App\Livewire\AdminFeedbacks;
use App\Livewire\Admin\ProductEmailsCenter;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Feedback;
use App\Models\RendezVous;
use Barryvdh\DomPDF\Facade\Pdf;

Route::middleware(['role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', \App\Livewire\AdminDashboard::class)->name('dashboard');

        if (class_exists(PlanningAdmin::class)) {
            Route::get('/planning', PlanningAdmin::class)->name('planning');
            Route::get('/calendar', PlanningAdmin::class)->name('calendar');
        }

        if (class_exists(GestionUtilisateurs::class)) {
            Route::get('/utilisateurs', GestionUtilisateurs::class)->name('utilisateurs');
        }

        if (class_exists(AdminFeedbacks::class)) {
            Route::get('/feedbacks', AdminFeedbacks::class)->name('feedbacks');
        }

        if (class_exists(OutilsAdmin::class)) {
            Route::get('/outils', OutilsAdmin::class)->name('outils');
        }

        if (class_exists(\App\Livewire\Admin\MissionsAdmin::class)) {
            Route::get('/missions', \App\Livewire\Admin\MissionsAdmin::class)->name('missions');
        }

        if (class_exists(MissionAdminController::class)) {
            Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])
                ->middleware('can:view,mission')
                ->name('missions.show');
        }

        if (class_exists(\App\Livewire\Admin\AdminAlertsCenter::class)) {
            Route::get('/alerts', \App\Livewire\Admin\AdminAlertsCenter::class)->name('alerts');
        }

        if (class_exists(\App\Livewire\Admin\AdminAnalyticsDashboard::class)) {
            Route::get('/analytics', \App\Livewire\Admin\AdminAnalyticsDashboard::class)->name('analytics');
        }

        if (class_exists(\App\Livewire\Admin\CustomerCreditsManager::class)) {
            Route::get('/credits-clients', \App\Livewire\Admin\CustomerCreditsManager::class)->name('customer.credits');
        }

        if (class_exists(\App\Livewire\Admin\StripeConnectProviders::class)) {
            Route::get('/stripe-connect-providers', \App\Livewire\Admin\StripeConnectProviders::class)->name('stripe-connect.providers');
        }

        if (class_exists(\App\Livewire\Admin\AiDispatchCenter::class)) {
            Route::get('/ia-dispatch', \App\Livewire\Admin\AiDispatchCenter::class)->name('ai.dispatch');
        }

        if (class_exists(\App\Livewire\Admin\BusinessDashboard::class)) {
            Route::get('/business-dashboard', \App\Livewire\Admin\BusinessDashboard::class)->name('business.dashboard');
        }

        if (class_exists(\App\Livewire\Admin\PlatformReadiness::class)) {
            Route::get('/platform-readiness', \App\Livewire\Admin\PlatformReadiness::class)->name('platform.readiness');
        }

        if (class_exists(\App\Livewire\Admin\B2BMonthlyInvoicesCenter::class)) {
            Route::get('/b2b/facturation-mensuelle', \App\Livewire\Admin\B2BMonthlyInvoicesCenter::class)->name('b2b.monthly-invoices');
        }

        if (class_exists(\App\Livewire\Admin\EnterpriseApprovalsCenter::class)) {
            Route::get('/approbations-entreprises', \App\Livewire\Admin\EnterpriseApprovalsCenter::class)->name('enterprise.approvals');
        }

        if (class_exists(\App\Livewire\Admin\OrganizationSitesManager::class)) {
            Route::get('/sites', \App\Livewire\Admin\OrganizationSitesManager::class)->name('sites');
        }
        Route::get('/premium-clients', function () {
            abort_unless(auth()->user()?->isAdmin(), 403);

            return view('admin.placeholder', [
                'title' => 'Clients Premium',
                'subtitle' => 'Gestion des clients premium.',
            ]);
        })->name('premium.clients');

        Route::get('/services', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-services'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Catalogue',
                'title' => 'Services',
                'sections' => [
                    'Catalogue services',
                    'Prix et règles',
                ],
            ]);
        })->name('services');

        Route::get('/zones', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-services'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Zones',
                'title' => 'Zones',
                'sections' => [
                    'Zones de service',
                    'Couverture',
                ],
            ]);
        })->name('zones');

        Route::get('/countries', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-services'), 403);

            return view('admin.placeholder', [
                'title' => 'Pays',
                'subtitle' => 'Gestion internationale des pays et régions.',
            ]);
        })->name('countries');

        Route::get('/international', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-services'), 403);

            return view('admin.placeholder', [
                'title' => 'International',
                'subtitle' => 'Pilotage international CleanUx.',
            ]);
        })->name('international');

        Route::get('/teams-partners', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.placeholder', [
                'title' => 'Équipes & partenaires',
                'subtitle' => 'Gestion des équipes terrain et partenaires.',
            ]);
        })->name('teams.partners');

        Route::get('/b2b-operations', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.placeholder', [
                'title' => 'Opérations B2B',
                'subtitle' => 'Centre opérationnel entreprises.',
            ]);
        })->name('b2b.operations');

        Route::get('/orchestration', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.placeholder', [
                'title' => 'Orchestration terrain',
                'subtitle' => 'Coordination des opérations terrain.',
            ]);
        })->name('orchestration');

        Route::get('/automation', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.placeholder', [
                'title' => 'Automatisation',
                'subtitle' => 'Automatisations opérationnelles.',
            ]);
        })->name('automation');

        Route::get('/modules', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-modules'), 403);

            return view('admin.placeholder', [
                'title' => 'Modules plateforme',
                'subtitle' => 'Configuration des modules CleanUx.',
            ]);
        })->name('modules');

        Route::get('/emails', ProductEmailsCenter::class)->name('emails');

        Route::get('/export/csv', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_if($user->isReadOnlyAdmin(), 403);

            $query = \App\Models\RendezVous::query()
                ->with(['client', 'employe', 'serviceZone']);

            if ($user->isZoneScopedAdmin()) {
                $query->where('service_zone_id', $user->managed_service_zone_id);
            }

            return response()->streamDownload(function () use ($query) {
                echo "id,client,employe,zone,status\n";

                foreach ($query->cursor() as $rdv) {
                    echo implode(',', [
                        $rdv->id,
                        '"' . str_replace('"', '""', $rdv->client?->name ?? '') . '"',
                        '"' . str_replace('"', '""', $rdv->employe?->name ?? '') . '"',
                        '"' . str_replace('"', '""', $rdv->serviceZone?->name ?? '') . '"',
                        $rdv->status,
                    ]) . "\n";
                }
            }, 'rendez-vous.csv', [
                'Content-Type' => 'text/csv',
            ]);
        })->name('export.csv');

        Route::get('/export/pdf', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_if($user->isReadOnlyAdmin(), 403);

            $query = RendezVous::query()->with(['client', 'employe', 'serviceZone']);

            if ($user->isZoneScopedAdmin()) {
                $query->where('service_zone_id', $user->managed_service_zone_id);
            }

            $rows = $query->limit(200)->get();

            $html = view('exports.rendez-vous-pdf', [
                'rows' => $rows,
            ])->render();

            return Pdf::loadHTML($html)->download('rendez-vous.pdf');
        })->name('export.pdf');

        Route::get('/feedbacks/export-csv', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_if($user->isReadOnlyAdmin(), 403);

            $query = \App\Models\Feedback::query()
                ->with(['client', 'rendezVous.serviceZone']);

            if ($user->isZoneScopedAdmin()) {
                $query->whereHas('rendezVous', function ($q) use ($user) {
                    $q->where('service_zone_id', $user->managed_service_zone_id);
                });
            }

            $csv = "id,client,note,commentaire,zone\n";

            foreach ($query->get() as $feedback) {
                $csv .= implode(',', [
                    $feedback->id,
                    '"' . str_replace('"', '""', $feedback->client?->name ?? '') . '"',
                    $feedback->note,
                    '"' . str_replace('"', '""', $feedback->commentaire ?? '') . '"',
                    '"' . str_replace('"', '""', $feedback->rendezVous?->serviceZone?->name ?? '') . '"',
                ]) . "\n";
            }

            return new \Symfony\Component\HttpFoundation\Response(
                $csv,
                200,
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="feedbacks.csv"',
                ]
            );
        })->name('feedbacks.export.csv');

        Route::get('/feedbacks/export', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_if($user->isReadOnlyAdmin(), 403);

            $feedbacks = Feedback::query()
                ->with(['client', 'rendezVous.serviceZone'])
                ->when($user->isZoneScopedAdmin(), function ($query) use ($user) {
                    $query->whereHas('rendezVous', function ($q) use ($user) {
                        $q->where('service_zone_id', $user->managed_service_zone_id);
                    });
                })
                ->limit(200)
                ->get();

            $html = view('exports.feedbacks-pdf', [
                'feedbacks' => $feedbacks,
            ])->render();

            return Pdf::loadHTML($html)->download('feedbacks.pdf');
        })->name('feedbacks.export');
        if (class_exists(\App\Livewire\Admin\CountryOperationsCenter::class)) {
            Route::get('/countries', \App\Livewire\Admin\CountryOperationsCenter::class)
                ->name('countries');
        }

        if (class_exists(\App\Livewire\Admin\B2BOperationsCenter::class)) {
            Route::get('/b2b-operations', \App\Livewire\Admin\B2BOperationsCenter::class)
                ->name('b2b.operations');
        }

        if (class_exists(\App\Livewire\Admin\ProductEmailsCenter::class)) {
            Route::get('/emails', \App\Livewire\Admin\ProductEmailsCenter::class)
                ->name('emails');
        }

        Route::get('/finance', function () {
            abort_unless(auth()->user()?->isAdmin(), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Finance',
                'title' => 'Centre finance',
                'sections' => [
                    'Pipeline finance',
                    'Workspace finance',
                ],
            ]);
        })->name('finance');

        Route::get('/audit/logs', function () {
            abort_unless(auth()->user()?->isAdmin(), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Audit',
                'title' => 'Audit logs',
                'sections' => [
                    'Activité récente',
                    'Traçabilité',
                ],
            ]);
        })->name('audit.logs');

        Route::get('/premium-clients', function () {
            abort_unless(auth()->user()?->isAdmin(), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Premium',
                'title' => 'Clients Premium',
                'sections' => [
                    'Abonnements',
                    'Avantages premium',
                ],
            ]);
        })->name('premium.clients');

        Route::get('/teams-partners', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Réseau terrain',
                'title' => 'Équipes terrain & partenaires',
                'sections' => [
                    'Équipes internes',
                    'Partenaires externes',
                ],
            ]);
        })->name('teams.partners');

        Route::get('/international', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-services'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'International',
                'title' => 'International exploitable',
                'sections' => [
                    'Readiness',
                    'Pays actifs',
                ],
            ]);
        })->name('international');

        Route::get('/orchestration', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Terrain',
                'title' => 'Orchestration terrain',
                'sections' => [
                    'Coordination',
                    'Dispatch',
                ],
            ]);
        })->name('orchestration');

        Route::get('/automation', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-entreprises'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Automatisation',
                'title' => 'Automatisation',
                'sections' => [
                    'Règles',
                    'Workflows',
                ],
            ]);
        })->name('automation');

        Route::get('/modules', function () {
            $user = auth()->user();

            abort_unless($user?->isAdmin(), 403);
            abort_unless($user->canAccessAdminModule('manage-modules'), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Plateforme',
                'title' => 'Centre de contrôle des modules',
                'sections' => [
                    'Audience modules',
                    'Règles de visibilité',
                ],
            ]);
        })->name('modules');

        Route::get('/rendez-vous/series/{series}/edit', function ($series) {
            abort_unless(auth()->user()?->isAdmin(), 403);

            return view('admin.simple-center', [
                'eyebrow' => 'Récurrence',
                'title' => 'Modifier la série récurrente',
                'sections' => [
                    'Occurrences',
                    'Règles de série',
                ],
            ]);
        })->name('rendezvous.series.edit');
    });
