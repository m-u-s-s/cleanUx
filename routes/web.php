<?php

use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Controllers\SharedTrackingController;
use App\Http\Controllers\WebViewEntryController;
use App\Livewire\Auth\VerifyPhone;
use App\Livewire\DesignSystem;
use App\Livewire\OrderEngine\AsapSearch;
use App\Livewire\OrderEngine\OrderConfirmation;
use App\Livewire\OrderEngine\OrderJourney;
use App\Livewire\Provider\FaceCheckPage;
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

    /*
     * LE PARCOURS DE REMEDIATION DU CONTROLE FACIAL — et il doit exister.
     *
     * `EnsureFaceCheckPassed` et `FaceCheckRequiredException` redirigent une session web vers
     * `provider.face-check`. Sans cette route, la redirection retombe sur l'accueil et le
     * prestataire tourne en rond sans jamais comprendre ce qu'on lui demande.
     *
     * Hors de `face.verified`, evidemment : l'y soumettre enfermerait le compte dans une boucle ou
     * l'on exige un controle sans jamais laisser le passer. Meme raison que le KYC hors de
     * `provider.approved`.
     */
    Route::get('/provider/verification-faciale', FaceCheckPage::class)
        ->name('provider.face-check');
});

// `enforce_2fa` comme les autres pages d'administration : la page ne montre pas de données, mais une
// exception sans raison est une exception qu'on recopiera ailleurs en croyant qu'elle est la règle.
Route::middleware(['auth', 'role:admin', 'enforce_2fa'])->group(function () {
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

/*
 * L'écran d'attente d'une course immédiate. Authentifié : la recherche n'existe qu'après
 * confirmation, donc après le compte. La propriété est revérifiée dans le composant.
 */
Route::get('/commander/recherche/{request}', AsapSearch::class)
    ->middleware('auth')
    ->name('order.asap.search');

Route::get('/commander/{sector?}/{trade?}', OrderJourney::class)
    ->name('order.journey');

/*
 * ACCEPTATION D'UNE INVITATION À REJOINDRE UNE SOCIÉTÉ.
 *
 * Volontairement sous le seul middleware `auth` : la recrue qui clique sur le lien reçu par email
 * n'a pas encore vérifié son téléphone ni terminé son onboarding. L'enfermer derrière
 * `verified` + `phone.verified` la renverrait vers un parcours sans rapport et lui ferait perdre
 * son invitation. Le jeton est nominatif et vérifié dans le contrôleur.
 */
Route::get('/invitations/{token}', [OrganizationInvitationController::class, 'accept'])
    ->middleware('auth')
    ->name('organization.invitations.accept');

/*
|--------------------------------------------------------------------------
| Suivi partagé (E3) — le patron « suivez ma course »
|--------------------------------------------------------------------------
|
| PUBLIQUE, MAIS PAS OUVERTE. `signed` refuse tout ce qui n'a pas été émis par la plateforme et
| tout ce qui a expiré : un identifiant de réservation dans une URL publique se devine en
| comptant, un lien signé non.
|
| AUCUNE AUTHENTIFICATION, ET C'EST TOUT L'INTÉRÊT. Le destinataire est la personne chez qui
| l'intervention a lieu — souvent quelqu'un qui n'a pas de compte et n'en veut pas. Lui demander
| de s'inscrire pour savoir à quelle heure sonner reviendrait à ne pas partager du tout.
*/
Route::get('/suivi/{booking}', SharedTrackingController::class)
    ->middleware('signed')
    ->name('tracking.shared');
