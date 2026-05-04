<?php

use App\Livewire\ClientCompany\BillingCenter;
use App\Livewire\ClientCompany\BookingHub;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Livewire\ClientCompany\MembersAccess;
use App\Livewire\ClientCompany\SiteManager;
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
Route::middleware(['auth', 'verified', 'active.account'])
    ->prefix('dashboard/entreprise-client')
    ->name('client-company.')
    ->group(function () {

        Route::get('/', ClientCompanyDashboard::class)->name('dashboard');
        Route::get('/locaux', SiteManager::class)->name('sites');
        Route::get('/reservations', BookingHub::class)->name('bookings.index');
        Route::get('/reservations/nouveau', BookingHub::class)->name('bookings.create');
        Route::get('/membres', MembersAccess::class)->name('members');
        Route::get('/facturation', BillingCenter::class)->name('billing');
    });

/*
|--------------------------------------------------------------------------
| Routes — Entreprise prestataire (provider_company)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'active.account'])
    ->prefix('dashboard/entreprise-prestataire')
    ->name('provider-company.')
    ->group(function () {

        Route::get('/', ProviderDashboard::class)->name('dashboard');
        Route::get('/canaux', TeamChannels::class)->name('channels');
        Route::get('/taches', TaskBoard::class)->name('tasks');
        Route::get('/dispatch', DispatchCenter::class)->name('dispatch');
        Route::get('/equipe', TeamManagement::class)->name('team');
    });
