<?php

use App\Http\Controllers\GoogleCalendarAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/google/calendar/connect', [GoogleCalendarAuthController::class, 'redirect'])
    ->name('google.calendar.connect');

Route::get('/google/calendar/callback', [GoogleCalendarAuthController::class, 'callback'])
    ->name('google.calendar.callback');

Route::match(['POST', 'DELETE'], '/google/calendar/disconnect', [GoogleCalendarAuthController::class, 'disconnect'])
    ->name('google.calendar.disconnect');

Route::middleware(['role:admin'])->group(function () {
    $googleAgendaSettings = class_exists(\App\Livewire\Admin\GoogleAgendaSettings::class)
        ? \App\Livewire\Admin\GoogleAgendaSettings::class
        : function () {
            abort(501, 'La page Google Agenda admin n’est pas encore disponible.');
        };

    Route::get('/admin/calendar/settings', $googleAgendaSettings)
        ->name('admin.calendar.settings');
});

Route::middleware(['role:employe'])->group(function () {
    $googleAgendaEmploye = class_exists(\App\Livewire\Employe\GoogleAgendaEmploye::class)
        ? \App\Livewire\Employe\GoogleAgendaEmploye::class
        : function () {
            abort(501, 'La page Google Agenda employé n’est pas encore disponible.');
        };

    Route::get('/dashboard/employe/google-calendar', $googleAgendaEmploye)
        ->name('employe.google.calendar');
});