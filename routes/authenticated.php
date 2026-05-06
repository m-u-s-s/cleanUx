<?php

use App\Http\Controllers\PresenceController;
use App\Http\Controllers\Messaging\AttachmentDownloadController;
use App\Livewire\NotificationsCenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Assistant\AssistantStreamController;

Route::get('/dashboard', function (Request $request) {
    $user = $request->user();

    if (! $user instanceof User) {
        abort(403);
    }

    if ($user->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    if ($user->isClient()) {
        return redirect()->route('client.dashboard');
    }

    if ($user->isEmploye()) {
        return redirect()->route('employe.dashboard');
    }


    abort(403);
})->name('dashboard');

Route::middleware(['auth', 'assistant.ratelimit'])->group(function () {
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
    Route::post('/presence/touch',  [PresenceController::class, 'touch'])->name('presence.touch');
    Route::post('/presence/status', [PresenceController::class, 'setStatus'])->name('presence.status');
    Route::get('/presence/me',     [PresenceController::class, 'me'])->name('presence.me');

    // Phase 4 — Download de pièce jointe via URL signée (15 min de validité).
    // Le middleware 'signed' valide la signature URL générée par
    // URL::temporarySignedRoute('messaging.attachments.download', ...).
    Route::get('/attachments/{attachment}', [AttachmentDownloadController::class, 'download'])
        ->middleware('signed')
        ->name('messaging.attachments.download');
});
