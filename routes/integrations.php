<?php

use App\Http\Controllers\GoogleCalendarAuthController;
use App\Livewire\Admin\GoogleAgendaSettings;
use App\Livewire\Employe\GoogleAgendaEmploye;
use Illuminate\Support\Facades\Route;

Route::get('/google/calendar/connect', [GoogleCalendarAuthController::class, 'redirect'])
    ->name('google.calendar.connect');

Route::get('/google/calendar/callback', [GoogleCalendarAuthController::class, 'callback'])
    ->name('google.calendar.callback');

Route::match(['POST', 'DELETE'], '/google/calendar/disconnect', [GoogleCalendarAuthController::class, 'disconnect'])
    ->name('google.calendar.disconnect');

// `module_gate` : meme porte que le groupe principal de `routes/admin.php`. Ce fichier sert des
// modules CATALOGUES -- `admin.finance` en est un -- et l'oublier ici laissait leur ecran ouvert
// pendant que la navigation cachait leur tuile : une porte invisible mais deverrouillee, c'est-a-dire
// l'inverse exact du defaut qu'on corrige. L'intermediaire est sans effet sur une route qui ne
// declare aucune capacite.
// `enforce_2fa` MANQUAIT : sur 110 routes d'administration web, celle-ci etait la SEULE sans.
// Elle ouvre le reglage d'une integration de calendrier — la creation d'un jeton OAuth au nom
// de la plateforme. Un administrateur non enrole etait renvoye vers l'activation partout
// ailleurs, et passait ici.
Route::middleware(['role:admin', 'enforce_2fa', 'module_gate'])->group(function () {
    $googleAgendaSettings = class_exists(GoogleAgendaSettings::class)
        ? GoogleAgendaSettings::class
        : function () {
            abort(501, 'La page Google Agenda admin n’est pas encore disponible.');
        };

    Route::get('/admin/calendar/settings', $googleAgendaSettings)
        ->name('admin.calendar.settings');
});

Route::middleware(['role:employe'])->group(function () {
    $googleAgendaEmploye = class_exists(GoogleAgendaEmploye::class)
        ? GoogleAgendaEmploye::class
        : function () {
            abort(501, 'La page Google Agenda employé n’est pas encore disponible.');
        };

    Route::get('/dashboard/employe/google-calendar', $googleAgendaEmploye)
        ->name('employe.google.calendar');
});
