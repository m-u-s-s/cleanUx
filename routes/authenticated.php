<?php

use App\Http\Controllers\Assistant\AssistantStreamController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Messaging\AttachmentDownloadController;
use App\Http\Controllers\PresenceController;
use App\Livewire\NotificationsCenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    if (! $user instanceof User) {
        abort(403);
    }

    /*
     * CHACUN CHEZ LUI, ET LA TABLE DE CORRESPONDANCE VIT DANS `Role`.
     *
     * Cet aiguillage testait trois cas — administrateur, client, prestataire — puis refusait le
     * reste par un 403. Deux conséquences, corrigées ici :
     *
     *   - `isClient()` est vrai pour un membre de société cliente (il délègue à
     *     `isClientCompany()`), qui atterrissait donc dans l'espace PERSONNEL ; son espace société
     *     n'était atteignable qu'en tapant l'URL. Même chose côté prestataire.
     *   - un super administrateur passait par `isAdmin()`, comme un administrateur ordinaire.
     *
     * Le 403 final disparaît : un compte tout juste créé, sans profil d'aucune sorte, cochait
     * zéro cas et se retrouvait enfermé dehors. `roleCanonique()` rend toujours un rôle.
     */
    $route = $user->roleCanonique()->routeDuTableauDeBord();

    // Une route absente vaut mieux signalée qu'en 500 : on retombe sur l'espace personnel, qui
    // existe toujours.
    return redirect()->route(Route::has($route) ? $route : 'client.dashboard');
})->name('dashboard');

Route::middleware(['auth', 'signed'])->group(function () {
    Route::get('/assistant/stream', [AssistantStreamController::class, 'stream'])
        ->name('assistant.stream');
});

Route::get('/notifications', NotificationsCenter::class)
    ->name('notifications.index');

Route::put('/current-team', function (Request $request) {
    $user = $request->user();

    if (! $user) {
        abort(403);
    }

    if (! method_exists($user, 'switchTeam')) {
        return back()->with('info', 'La gestion des équipes Jetstream n’est pas activée.');
    }

    $teamId = $request->integer('team_id');

    if (! $teamId) {
        return back()->with('error', 'Équipe invalide.');
    }

    $team = $user->allTeams()
        ->where('id', $teamId)
        ->first();

    if (! $team) {
        abort(403);
    }

    $user->switchTeam($team);

    return back()->with('success', 'Équipe active mise à jour.');
})->name('current-team.update');

Route::middleware('auth')->group(function () {
    Route::post('/presence/touch', [PresenceController::class, 'touch'])->name('presence.touch');
    Route::post('/presence/status', [PresenceController::class, 'setStatus'])->name('presence.status');
    Route::get('/presence/me', [PresenceController::class, 'me'])->name('presence.me');

    // Phase 4 — Download de pièce jointe via URL signée (15 min de validité).
    // Le middleware 'signed' valide la signature URL générée par
    // URL::temporarySignedRoute('messaging.attachments.download', ...).
    Route::get('/attachments/{attachment}', [AttachmentDownloadController::class, 'download'])
        ->middleware('signed')
        ->name('messaging.attachments.download');

    // M3 — authenticated streaming of mission/dispute photos on the private disk, via a
    // short-lived signed URL (replaces the old public /storage/* links).
    Route::get('/media/private', [MediaController::class, 'show'])
        ->middleware('signed')
        ->name('media.private.show');
});
