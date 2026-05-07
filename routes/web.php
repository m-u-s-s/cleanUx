<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Push\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Central router: only loads domain-specific route files
*/

require __DIR__ . '/public.php';

Route::middleware(['auth', 'verified', 'active.account'])->group(function () {

    require __DIR__ . '/authenticated.php';
    require __DIR__ . '/integrations.php';

    require __DIR__ . '/admin.php';
    require __DIR__ . '/client.php';
    require __DIR__ . '/employe.php';

    require __DIR__ . '/feedback.php';
    require __DIR__ . '/missions.php';

    require __DIR__ . '/company-dashboards.php';
    require __DIR__ . '/missing-route-fixes-advanced.php';

    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
});

Route::middleware('auth')->prefix('push')->group(function () {
    Route::post('/subscribe',   [PushSubscriptionController::class, 'subscribe']);
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/test',        [PushSubscriptionController::class, 'test']);
});

// Public (pas besoin d'auth pour récupérer la clé publique)
Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);