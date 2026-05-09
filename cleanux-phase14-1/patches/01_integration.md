# Phase 14.1 — Patches manuels d'intégration

## 1. routes/admin.php — ajouter les routes admin onboarding

Dans le bloc `Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(...)`,
ajouter :

```php
use App\Http\Controllers\Admin\OnboardingDocumentController;
use App\Livewire\Admin\Onboarding\AdminOnboardingDocumentsCenter;
use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;

// Phase 14.1 — Onboarding admin
Route::get('/onboarding-providers',  AdminOnboardingProvidersList::class)
    ->name('onboarding.providers');

Route::get('/onboarding-documents',  AdminOnboardingDocumentsCenter::class)
    ->name('onboarding.documents');

// Téléchargement de fichier privé via URL signée temporaire
Route::get('/onboarding-documents/{document}/file', [OnboardingDocumentController::class, 'show'])
    ->middleware('signed')
    ->name('onboarding.document.file');
```

## 2. routes/web.php — wizard côté provider

Ajouter (en dehors de tout middleware role car le user n'est pas encore "provider"
au sens rôle, juste authentifié) :

```php
use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;

Route::middleware(['auth'])->group(function () {
    Route::get('/provider/onboarding', ProviderOnboardingWizard::class)
        ->name('provider.onboarding');
});
```

## 3. Menu de navigation admin

Dans ton menu admin (`resources/views/...` selon ton organisation), ajouter
les liens :

```blade
<a href="{{ route('admin.onboarding.providers') }}"
   class="...">
    👥 Onboarding prestataires
    @if ($pendingCount = \App\Models\ProviderOnboardingDocument::where('status', 'pending_review')->count())
        <span class="ml-2 inline-flex rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs">
            {{ $pendingCount }}
        </span>
    @endif
</a>
```

## 4. Redirection après inscription user

Si tu veux que les nouveaux users soient redirigés directement vers le wizard
après inscription (au lieu du dashboard), modifier ton `LoginResponse` /
`RegisterResponse` Fortify ou le middleware `RedirectIfAuthenticated` :

```php
// App\Http\Responses\LoginResponse ou via RouteServiceProvider::HOME

public function toResponse($request)
{
    $user = $request->user();

    // Phase 14.1 — Si provider en cours d'onboarding, rediriger là
    if ($user->providerProfile
        && $user->providerProfile->verification_status !== 'verified'
        && $user->providerProfile->onboarding_step < 6) {
        return redirect()->route('provider.onboarding');
    }

    return redirect()->intended(config('fortify.home'));
}
```

(Optionnel — laisse en l'état si tu préfères que le user navigue lui-même.)

## 5. Composant "Mon onboarding" dans le menu provider

Pour que le provider en cours d'onboarding ait un raccourci visible, ajouter
dans le menu sidebar (provider) :

```blade
@auth
    @if (auth()->user()->providerProfile && auth()->user()->providerProfile->verification_status !== 'verified')
        <a href="{{ route('provider.onboarding') }}"
           class="rounded-2xl border-2 border-amber-300 bg-amber-50 px-4 py-3 text-sm">
            <div class="font-semibold text-amber-900">📋 Mon inscription</div>
            <div class="text-xs text-amber-700 mt-0.5">
                Étape {{ auth()->user()->providerProfile->onboarding_step }} / 6
            </div>
        </a>
    @endif
@endauth
```

## 6. Storage public link (pour photo de profil)

La photo de profil est stockée sur disk `public`, donc accessible via
`/storage/...` après symlink :

```bash
php artisan storage:link
```

(À faire une seule fois).

## 7. Tests

```bash
php artisan test --filter=Phase14_1Test
```

15 tests doivent passer (5 admin docs, 4 admin providers list, 6 wizard provider).

## 8. Limites Phase 14.1

- **Pas de bulk actions** (approuver 10 docs d'un coup) : ajout facile si
  besoin volume (~50 lignes).
- **Pas d'historique des actions admin** : le service garde déjà
  `reviewed_by` + `reviewed_at`, mais pas de log structuré ailleurs. Pour
  audit complet, brancher Phase 4 (audit logs) si appliquée.
- **Pas de notification au provider** quand un doc est approuvé/rejeté :
  ajout simple (`$provider->notify(new DocumentReviewedNotification(...))`)
  mais nécessite de créer la classe Notification. Reportée à Phase 14.2 si
  demandée.
- **Wizard pas vraiment "responsive mobile"** : ça marche mais l'UX peut être
  améliorée (sticky progress, scroll auto).
- **Photo profil** : stockée mais pas affichée nulle part dans l'UI client
  pour l'instant. À ajouter dans `MissionOfferPage` Phase 11 si tu veux que
  le client voie le visage de son prestataire.
- **Pas de webhook Stripe pour update auto stripe_connect_status** :
  actuellement le wizard re-check via `refreshStripeStatus`. Phase 13
  webhook `account.updated` peut updater silencieusement → la prochaine
  page-load montre le nouveau statut.
