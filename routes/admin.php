<?php

use App\Http\Controllers\Admin\MissionAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', \App\Livewire\AdminDashboard::class)->name('dashboard');

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
    });