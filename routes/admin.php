<?php

use App\Http\Controllers\Admin\MissionAdminController;
use App\Models\RendezVous;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', \App\Livewire\AdminDashboard::class)->name('dashboard');

        if (class_exists(\App\Livewire\Admin\MissionsAdmin::class)) {
            Route::get('/missions', \App\Livewire\Admin\MissionsAdmin::class)->name('missions');
        } else {
            Route::get('/missions', function () {
                abort(501, 'La page missions admin n’est pas encore disponible.');
            })->name('missions');
        }

        if (class_exists(MissionAdminController::class)) {
            Route::get('/missions/{mission}', [MissionAdminController::class, 'show'])
                ->middleware('can:view,mission')
                ->name('missions.show');
        }

        Route::get('/missions/export/pdf', function () {
            if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
                $html = '
                    <h1>Export missions</h1>
                    <p>Export PDF temporaire. À remplacer par un vrai export filtré.</p>
                ';

                return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                    ->download('missions-export.pdf');
            }

            abort(501, 'Export PDF missions pas encore implémenté.');
        })->name('missions.export.pdf');

        Route::get('/quality/export/incidents.csv', function () {
            return response()->streamDownload(function () {
                echo "id,mission_id,type,status,created_at\n";
            }, 'incidents.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        })->name('quality.export.incidents.csv');

        Route::get('/quality/export/missions.csv', function () {
            return response()->streamDownload(function () {
                echo "id,reference,status,quality_score,created_at\n";
            }, 'missions-quality.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        })->name('quality.export.missions.csv');

        Route::get('/rendez-vous/{rendezVous}', function (RendezVous $rendezVous) {
            if (Route::has('admin.missions')) {
                return redirect()->route('admin.missions');
            }

            return redirect()->route('admin.dashboard');
        })->name('rendezvous.show');

        $utilisateursAdmin = class_exists(\App\Livewire\Admin\UtilisateursAdmin::class)
            ? \App\Livewire\Admin\UtilisateursAdmin::class
            : function () {
                abort(501, 'La page gestion utilisateurs n’est pas encore disponible.');
            };

        Route::get('/utilisateurs', $utilisateursAdmin)
            ->name('utilisateurs.manage');

        Route::get('/users', function () {
            return redirect()->route('admin.utilisateurs.manage');
        })->name('utilisateurs');

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
            Route::get('/b2b/facturation-mensuelle', \App\Livewire\Admin\B2BMonthlyInvoicesCenter::class)
                ->name('b2b.monthly-invoices');
        }

        if (class_exists(\App\Livewire\Admin\EnterpriseApprovalsCenter::class)) {
            Route::get('/approbations-entreprises', \App\Livewire\Admin\EnterpriseApprovalsCenter::class)
                ->name('enterprise.approvals');
        }

        if (class_exists(\App\Livewire\Admin\OrganizationSitesManager::class)) {
            Route::get('/sites', \App\Livewire\Admin\OrganizationSitesManager::class)->name('sites');
        }
    });