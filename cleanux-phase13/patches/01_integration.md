# Phase 13 — Patches manuels d'intégration

## 1. routes/api.php — ajouter les payouts endpoints

Dans le bloc `Route::middleware('auth:sanctum')->group(...)`, ajouter :

```php
use App\Http\Controllers\Api\Provider\ProviderPayoutsController;

Route::prefix('provider/payouts')->group(function () {
    Route::get('/',         [ProviderPayoutsController::class, 'index']);
    Route::get('/summary',  [ProviderPayoutsController::class, 'summary']);
});
Route::get('/provider/balance', [ProviderPayoutsController::class, 'balance']);
```

## 2. routes/web.php — webhook Stripe Connect + page payouts

```php
use App\Http\Controllers\Webhooks\StripeConnectWebhookController;
use App\Livewire\Provider\ProviderPayoutsPage;

// Webhook (PAS de middleware auth ni csrf — Stripe vérifie via signature)
Route::post('/webhooks/stripe-connect', [StripeConnectWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.stripe-connect');

// Page web payouts pour le prestataire
Route::middleware(['auth'])->group(function () {
    Route::get('/provider/payouts', ProviderPayoutsPage::class)->name('provider.payouts');
});
```

## 3. CSRF exclusion

Si tu utilises Laravel 10 (avec VerifyCsrfToken middleware), ajouter dans
`app/Http/Middleware/VerifyCsrfToken.php` :

```php
protected $except = [
    // ... autres exclusions ...
    'webhooks/stripe-connect',
];
```

Pour Laravel 11+ (bootstrap/app.php) :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'webhooks/stripe-connect',
    ]);
})
```

## 4. config/services.php — ajouter les secrets Stripe Connect

```php
'stripe' => [
    'key'    => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'connect_webhook_secret' => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
],

'google_maps' => [
    'api_key' => env('GOOGLE_MAPS_API_KEY'),
],
```

## 5. .env — variables à ajouter

```ini
# Stripe Connect webhook (DIFFÉRENT du webhook subscription Cashier !)
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxx

# Google Maps Distance Matrix (optionnel — fallback Haversine si absent)
GOOGLE_MAPS_API_KEY=AIzaSy...

# URLs onboarding Stripe Connect (existait déjà)
STRIPE_CONNECT_REFRESH_URL=https://ton-app.com/provider/onboarding/refresh
STRIPE_CONNECT_RETURN_URL=https://ton-app.com/provider/onboarding/done

# Plate-forme fee (existait déjà — utilisé par MissionPaymentService)
CLEANUX_PLATFORM_FEE_PERCENT=20
```

## 6. app/Providers/AppServiceProvider.php — register Observer

Dans la méthode `boot()` :

```php
use App\Models\MissionTrackingPoint;
use App\Observers\MissionTrackingPointObserver;

public function boot(): void
{
    // ... autres bindings ...

    // Phase 13 — Observer pour calcul ETA à chaque ping GPS
    MissionTrackingPoint::observe(MissionTrackingPointObserver::class);
}
```

## 7. routes/channels.php — autoriser le channel mission.{id}

Pour que le client (web ou mobile) puisse écouter l'event `MissionEtaUpdated`
sur son channel privé :

```php
use App\Models\Mission;

Broadcast::channel('mission.{missionId}', function ($user, int $missionId) {
    $mission = Mission::find($missionId);
    if (! $mission) return false;

    // Le client (customer ou client_id du booking)
    $booking = $mission->booking;
    if ($booking) {
        if ((int) ($booking->customer_user_id ?? 0) === (int) $user->id) return true;
        if ((int) ($booking->client_id ?? 0) === (int) $user->id) return true;
    }

    // Le prestataire assigné
    if ((int) $mission->lead_provider_user_id === (int) $user->id) return true;

    // Autorisation via assignments
    if ($mission->assignments()->where('user_id', $user->id)->exists()) return true;

    // Admins
    if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) return true;

    return false;
});
```

## 8. config/cashier.php (à publier si absent)

Pour un Stripe Connect propre :

```bash
php artisan vendor:publish --tag="cashier-config"
```

Vérifier que `cashier.secret` est bien configuré (utilisé par `Stripe::setApiKey()`).

## 9. Configuration du webhook dans Stripe Dashboard

Dans le dashboard Stripe :

1. Aller sur https://dashboard.stripe.com/webhooks
2. Cliquer "Add endpoint"
3. URL : `https://ton-app.com/webhooks/stripe-connect`
4. **Listen to** : "Events on Connected accounts" (PAS account-level)
5. Sélectionner les events :
   - `account.updated`
   - `payout.paid`
   - `payout.failed`
   - `charge.refunded`
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
6. Récupérer le signing secret → `STRIPE_CONNECT_WEBHOOK_SECRET` dans .env

Pour tester en local : utiliser Stripe CLI :

```bash
stripe listen --forward-to localhost:8000/webhooks/stripe-connect \
  --events account.updated,payout.paid,charge.refunded
```

## 10. Brancher capture sur completion mission

Au moment où une mission passe en `completed`, on doit déclencher la capture.
Modifier `MissionLifecycleService::completeMission()` (ou l'appeler depuis là) :

```php
use App\Services\Payments\StripeConnectPaymentService;

public function completeMission(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
{
    // ... logique existante ...

    $mission->refresh();

    // Phase 13 — Auto-capture du PaymentIntent si autorisé
    if ($mission->booking?->payment_status === 'authorized') {
        try {
            app(StripeConnectPaymentService::class)->captureMissionPayment($mission);
        } catch (\Throwable $e) {
            \Log::error('Auto-capture après completion échouée', [
                'mission_id' => $mission->id,
                'error'      => $e->getMessage(),
            ]);
            // On NE bloque PAS la completion : la capture peut être retentée plus tard
        }
    }

    return $mission;
}
```

Alternative non-invasive : créer un Listener sur `MissionCompletedEvent` (à
créer si absent) qui déclenche `captureMissionPayment` en async via une queue.

## 11. Tester end-to-end

```bash
# Migrations
php artisan migrate

# Tests
php artisan test --filter=Phase13Test

# Test manuel webhook avec Stripe CLI
stripe trigger payout.paid

# Test API balance (nécessite stripe_connect_account_id sur le user)
curl -H "Authorization: Bearer $TOKEN" \
  https://ton-app.com/api/provider/balance
```

## 12. Limites Phase 13

- **Capture automatique** : la modification de `completeMission` est dans un
  patch manuel (pas appliqué automatiquement) car ça touche un service core.
  Tu peux préférer un job async (recommandé en prod : queue les captures pour
  pouvoir retry si Stripe est down).
- **Pas de gestion des disputes complexes** : Stripe disputes ouvrent une
  fenêtre de réponse de 7 à 21 jours. Phase 13 ne gère pas le workflow de
  réponse à dispute (à faire via dashboard Stripe pour l'instant).
- **Pas de Stripe Connect onboarding mobile** : l'onboarding link redirige
  vers une page Stripe → après c'est une URL de retour. Pour mobile pure, il
  faut le lien dans un `WKWebView` / `Custom Tab` natif.
- **ETA refresh limité** : le service cache 60s pour économiser le quota
  Google. Si tu veux du vrai temps réel sub-seconde, il faudrait du websocket
  côté Google et non Distance Matrix.
- **Refunds** : la méthode `refundMissionPayment` existe mais aucun endpoint
  API ne l'expose pour l'instant. À ajouter si tu veux que les admins puissent
  refund depuis l'app (vs dashboard Stripe).
