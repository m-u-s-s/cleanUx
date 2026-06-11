<?php

use App\Livewire\ClientCompany\Analytics\ClientAnalyticsDashboard;
use App\Livewire\ClientCompany\BillingCenter;
use App\Livewire\ClientCompany\BookingHub;
use App\Livewire\ClientCompany\BulkBookingImporter;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Livewire\ClientCompany\ClientContractsCenter;
use App\Livewire\ClientCompany\DisputesCenter;
use App\Livewire\ClientCompany\MembersAccess;
use App\Livewire\ClientCompany\SiteManager;
use App\Livewire\ClientCompany\SiteMissionPhotos;
use App\Livewire\ProviderCompany\DispatchCenter;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Livewire\ProviderCompany\TaskBoard;
use App\Livewire\ProviderCompany\TeamChannels;
use App\Livewire\ProviderCompany\TeamManagement;
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
        Route::get('/locaux', SiteManager::class)->name('sites');
        Route::get('/reservations', BookingHub::class)->name('bookings.index');
        Route::get('/reservations/nouveau', BookingHub::class)->name('bookings.create');
        Route::get('/membres', MembersAccess::class)->name('members');
        Route::get('/contrats', ClientContractsCenter::class)->name('contracts');
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
        Route::get('/canaux', TeamChannels::class)->name('channels');
        Route::get('/taches', TaskBoard::class)->name('tasks');
        Route::get('/dispatch', DispatchCenter::class)->name('dispatch');
        Route::get('/equipe', TeamManagement::class)->name('team');
    });
