<?php

use App\Http\Controllers\MissionFieldActionController;
use App\Http\Controllers\MissionTrackingController;
use App\Models\Mission;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

/*
 * LES GESTES DE TERRAIN DU WEB — la meme porte que l'API.
 *
 * `face.verified` ne couvre PAS tout le tableau de bord prestataire : consulter ses revenus
 * n'envoie personne chez un client, et rediriger quelqu'un vers un selfie parce qu'il regarde sa
 * paie serait une friction que rien ne justifie. Ce sont ces routes-ci qui comptent -- partir,
 * arriver, cloturer -- et elles sont les memes que celles de l'application mobile.
 *
 * Les gardes de service posees dans `MissionLifecycleService` tiennent de toute facon la ligne :
 * ce middleware est la pour EXPLIQUER le refus a un navigateur, pas pour l'imposer.
 */
Route::middleware(['role:employe', 'face.verified'])->group(function () {
    Route::post('/missions/offline-sync', [MissionFieldActionController::class, 'offlineSync'])
        ->name('missions.offline-sync');

    Route::post('/mission-checklist-items/{item}/toggle', [MissionFieldActionController::class, 'toggleChecklistItem'])
        ->name('missions.checklist-items.toggle');

    Route::post('/missions/{mission}/start', [MissionFieldActionController::class, 'start'])
        ->name('missions.start');

    Route::post('/missions/{mission}/en-route', [MissionFieldActionController::class, 'enRoute'])
        ->name('missions.en-route');

    Route::post('/missions/{mission}/arrived', [MissionFieldActionController::class, 'arrived'])
        ->name('missions.arrived');

    Route::post('/missions/{mission}/finish', [MissionFieldActionController::class, 'finish'])
        ->name('missions.finish');

    Route::get('/dashboard/employe/missions/{mission}/qr/{type}/{codeId}', function (Mission $mission, string $type, int $codeId) {
        abort_unless(in_array($type, ['start', 'end'], true), 404);

        return redirect()
            ->route('employe.missions.show', $mission)
            ->with('qr_type', $type)
            ->with('qr_code_id', $codeId)
            ->with('success', 'QR code détecté. Validez le code dans la mission.');
    })->middleware('can:update,mission')->name('employe.missions.qr.validate');

    Route::post('/missions/{mission}/tracking/start', [MissionTrackingController::class, 'start'])
        ->middleware('can:track,mission')
        ->name('missions.tracking.start');

    Route::post('/mission-tracking-sessions/{session}/tracking/push', [MissionTrackingController::class, 'push'])
        ->name('missions.tracking.push');

    Route::post('/mission-tracking-sessions/{session}/tracking/stop', [MissionTrackingController::class, 'stop'])
        ->name('missions.tracking.stop');
});

Route::get('/missions/{mission}', function (Mission $mission) {
    if (auth()->user()?->isAdmin() && Route::has('admin.missions.show')) {
        return redirect()->route('admin.missions.show', $mission);
    }

    if (auth()->user()?->isEmploye() && Route::has('employe.missions.show')) {
        return redirect()->route('employe.missions.show', $mission);
    }

    if (Route::has('missions.tracking.live')) {
        return redirect()->route('missions.tracking.live', $mission);
    }

    abort(404);
})->middleware('can:view,mission')->name('missions.show');

Route::get('/missions/{mission}/rapport/pdf', function (Mission $mission) {
    abort_unless(auth()->user()?->can('view', $mission), 403);

    $path = $mission->getAttribute('report_path')
        ?? $mission->getAttribute('pdf_path')
        ?? $mission->getAttribute('document_path');

    abort_unless(filled($path), 404, 'Rapport PDF introuvable.');

    $fullPath = storage_path('app/public/'.ltrim($path, '/'));

    abort_unless(file_exists($fullPath), 404, 'Fichier du rapport introuvable.');

    return Response::download($fullPath);
})->middleware('can:view,mission')->name('missions.report.pdf');

Route::get('/missions/{mission}/tracking/live', [MissionTrackingController::class, 'live'])
    ->middleware('can:view,mission')
    ->name('missions.tracking.live');
