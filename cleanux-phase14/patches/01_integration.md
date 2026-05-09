# Phase 14 — Patches manuels d'intégration

## 1. routes/api.php — ajouter les nouvelles routes

Dans le bloc `Route::middleware('auth:sanctum')->group(...)`, ajouter :

```php
use App\Http\Controllers\Api\Client\CancellationController;
use App\Http\Controllers\Api\Provider\ProviderCancellationController;
use App\Http\Controllers\Api\Provider\ProviderOnboardingController;

// Phase 14 — Cancellation client
Route::prefix('client/bookings')->group(function () {
    Route::get('/{booking}/cancellation-quote', [CancellationController::class, 'quote']);
    Route::post('/{booking}/cancel-with-fee',   [CancellationController::class, 'cancelWithFee']);
});

// Phase 14 — Cancellation provider
Route::prefix('provider/missions')->group(function () {
    Route::post('/{mission}/cancel',   [ProviderCancellationController::class, 'cancel']);
    Route::post('/{mission}/no-show',  [ProviderCancellationController::class, 'noShow']);
});

// Phase 14 — Onboarding provider
Route::prefix('provider/onboarding')->group(function () {
    Route::post('/start',     [ProviderOnboardingController::class, 'start']);
    Route::get('/progress',   [ProviderOnboardingController::class, 'progress']);
    Route::post('/profile',   [ProviderOnboardingController::class, 'setProfile']);
    Route::post('/documents', [ProviderOnboardingController::class, 'uploadDocument']);
    Route::post('/tax',       [ProviderOnboardingController::class, 'setTax']);
    Route::post('/skills',    [ProviderOnboardingController::class, 'setSkills']);
});
```

## 2. app/Console/Kernel.php — scheduler surge recompute

Dans `schedule()` :

```php
// Phase 14 — Recalcul surge pricing toutes les minutes
$schedule->command('surge:recompute')->everyMinute()->withoutOverlapping();
```

## 3. config/filesystems.php — disk 'private' pour les documents KYC

Si pas déjà présent, ajouter dans `config/filesystems.php` :

```php
'disks' => [
    // ... existing disks ...

    'private' => [
        'driver' => 'local',
        'root'   => storage_path('app/private'),
        'visibility' => 'private',
    ],
],
```

Pour servir les documents privés (vue admin uniquement), créer une route :

```php
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/onboarding-documents/{document}/file', function (
        \App\Models\ProviderOnboardingDocument $document
    ) {
        return response()->file(
            storage_path('app/private/' . $document->file_path)
        );
    })->name('admin.onboarding.document.file');
});
```

## 4. config/surge.php — déjà fourni

Le fichier `config/surge.php` est dans le pack. Tu peux ajuster les seuils
sans toucher au code.

## 5. config/cancellation.php — déjà fourni

Idem. Tous les % et délais sont config-driven.

## 6. .env — variables optionnelles

```ini
# Surge pricing
SURGE_MAX_MULTIPLIER=3.0
SURGE_TTL_SECONDS=600
SURGE_RECOMPUTE_SECONDS=60
```

## 7. Bind du DynamicPricingService remplaçant (optionnel)

Si ton code existant appelle `app(DynamicPricingService::class)->calculate(...)`,
tu peux faire un alias de service dans `AppServiceProvider::boot()` pour
le remplacer par SurgePricingEngine avec la signature compatible :

```php
public function boot(): void
{
    // Phase 14 — Aliaser l'ancien DynamicPricingService vers le nouveau
    // moteur Surge (signature backward-compatible)
    $this->app->bind(
        \App\Services\Pricing\DynamicPricingService\DynamicPricingService::class,
        function () {
            // Wrapper qui adapte la nouvelle signature
            return new class {
                public function calculate(float $basePrice, array $context): float
                {
                    $zone = isset($context['service_zone_id'])
                        ? \App\Models\ServiceZone::find($context['service_zone_id'])
                        : null;

                    $result = app(\App\Services\Pricing\SurgePricingEngine::class)
                        ->calculate($basePrice, $zone, $context);

                    return $result['final_price'];
                }
            };
        }
    );
}
```

Sinon, mettre à jour les call sites pour utiliser `SurgePricingEngine` directement
et bénéficier du retour structuré (multiplier, factors, is_visible).

## 8. UI client — afficher le surge

Dans le flow de création booking (`PrendreRendezVous` Livewire), avant
confirmation :

```blade
@php
    $surge = app(\App\Services\Pricing\SurgePricingEngine::class)
        ->calculate($basePrice, $serviceZone, ['booking_mode' => $bookingMode]);
@endphp

@if ($surge['is_visible'])
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm">
        ⚠ Tarifs élevés en ce moment ({{ round(($surge['multiplier'] - 1) * 100) }}%
        au-dessus de la base) en raison d'une forte demande.
        Prix : <strong>{{ number_format($surge['final_price'], 2, ',', ' ') }} €</strong>
    </div>
@endif
```

## 9. UI client — quote AVANT cancel

Dans la modale d'annulation :

```javascript
// Avant de confirmer cancel, fetch le quote
const quote = await fetch(`/api/client/bookings/${bookingId}/cancellation-quote`, {
    headers: { 'Authorization': `Bearer ${token}` },
}).then(r => r.json());

if (quote.quote.fee_amount > 0) {
    // Afficher modale "Tu seras facturé X€"
    confirm(`L'annulation entraînera des frais de ${quote.quote.fee_amount}€. Continuer ?`);
}

// Si confirmé
await fetch(`/api/client/bookings/${bookingId}/cancel-with-fee`, {
    method: 'POST',
    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ reason: 'changement de plans', accept_fee: true }),
});
```

## 10. Tests

```bash
php artisan migrate
php artisan test --filter=Phase14Test
```

22 tests doivent passer :
- 6 sur SurgePricingEngine
- 7 sur ProviderOnboardingService
- 9 sur CancellationFeeCalculator + CancelBookingService

## 11. Tester le surge en prod

```bash
# Forcer un recalcul manuel
php artisan surge:recompute --zone=42

# Voir l'état actuel d'une zone
php artisan tinker
>>> \App\Models\PricingZoneState::where('service_zone_id', 42)->first()->toArray();
```

## 12. Limites Phase 14

- **DynamicPricingService legacy** : pas supprimé, juste laissé en place. Le
  patch §7 propose un alias pour transparence. Une fois que tu as migré tous
  les call sites, tu peux le supprimer.
- **Pas d'UI admin pour valider les documents** : la validation passe par
  Tinker ou par un endpoint à créer (`POST /admin/onboarding-documents/{id}/review`).
- **Pas de rappel email/push aux providers en cours d'onboarding** : si un
  provider commence puis abandonne, aucune relance auto. À ajouter en Phase 14.1.
- **Pas de UI pour les cancellation fees côté admin** : si tu veux waiver un
  fee (geste commercial), il faut le faire via Tinker ou un endpoint custom.
- **Surge supply : approximatif** : le calcul de "online providers in zone"
  utilise `zoneAssignments` qui n'existe peut-être pas dans tous les schemas.
  Le service a un fallback "tous les providers online" mais idéalement c'est
  haversine vs zone polygon.
- **Reliability penalty** : stockée dans `provider_profiles.metadata` JSON.
  Pour exposition + admin UI, créer une colonne dédiée serait mieux. À
  faire en Phase 14.1.
