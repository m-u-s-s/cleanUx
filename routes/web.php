<?php

use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Controllers\WebViewEntryController;
use App\Livewire\Auth\VerifyPhone;
use App\Livewire\DesignSystem;
use App\Livewire\OrderEngine\OrderConfirmation;
use App\Livewire\OrderEngine\OrderJourney;
use App\Livewire\Provider\MissionOfferPage;
use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Central router: only loads domain-specific route files
*/

require __DIR__.'/public.php';

Route::middleware(['auth', 'verified', 'active.account', 'phone.verified'])->group(function () {

    require __DIR__.'/authenticated.php';
    require __DIR__.'/integrations.php';

    require __DIR__.'/admin.php';
    require __DIR__.'/client.php';
    require __DIR__.'/employe.php';

    require __DIR__.'/feedback.php';
    require __DIR__.'/missions.php';

    require __DIR__.'/company-dashboards.php';
    require __DIR__.'/missing-route-fixes-advanced.php';

});

Route::middleware('auth')->prefix('push')->group(function () {
    Route::post('/subscribe', [PushSubscriptionController::class, 'subscribe']);
    Route::post('/unsubscribe', [PushSubscriptionController::class, 'unsubscribe']);
    Route::post('/test', [PushSubscriptionController::class, 'test']);
});

// Public (pas besoin d'auth pour récupérer la clé publique)
Route::get('/push/public-key', [PushSubscriptionController::class, 'publicKey']);

// WebView session handoff — redeems a single-use ticket and logs the user in
Route::get('/m/enter', WebViewEntryController::class)->name('webview.enter');

// OTP téléphone — page de vérification (auth seule, hors garde phone.verified pour éviter une boucle)
Route::middleware('auth')->get('/verify-phone', VerifyPhone::class)->name('phone.verify');

Route::middleware(['auth', 'verified', 'active.account', 'phone.verified'])->group(function () {
    Route::get('/provider/missions/{assignment}/offer', MissionOfferPage::class)
        ->name('provider.missions.offer');

    Route::get('/provider/onboarding', ProviderOnboardingWizard::class)
        ->name('provider.onboarding');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/design-system', DesignSystem::class)->name('design-system');
});

/*
 * Parcours de commande — public, et volontairement sans authentification.
 *
 * Le client compose sa commande et voit son prix AVANT qu'on lui demande un compte : exiger une
 * identité ici replacerait le formulaire d'inscription devant l'estimation, c'est-à-dire devant la
 * première cause d'abandon. Le panier vit sur un jeton de session jusqu'à la confirmation.
 */
/*
 * DÉCLARÉE AVANT le parcours : `/commander/{sector?}` attraperait sinon « recapitulatif » comme un
 * nom de secteur, et le dernier écran deviendrait injoignable.
 *
 * Publique elle aussi : un visiteur non connecté doit voir son récapitulatif COMPLET, prix inclus,
 * avant de décider de créer un compte. L'identité est demandée DANS l'écran, pas devant.
 */
Route::get('/commander/recapitulatif', OrderConfirmation::class)
    ->name('order.confirmation');

Route::get('/commander/{sector?}/{trade?}', OrderJourney::class)
    ->name('order.journey');
