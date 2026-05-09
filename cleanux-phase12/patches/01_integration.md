# Phase 12 — Patches manuels d'intégration

## 1. routes/api.php — version complète Phase 12

Remplace le contenu de `routes/api.php` par :

```php
<?php

use App\Http\Controllers\Api\ApiNotificationController;
use App\Http\Controllers\Api\Auth\ApiAuthController;
use App\Http\Controllers\Api\Client\ClientBookingController;
use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Provider\ProviderMissionLifecycleController;
use App\Http\Controllers\Api\ProviderMissionAssignmentController;
use App\Http\Controllers\Api\ProviderPresenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─────────────────────────────────────────────
// Public — Auth
// ─────────────────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/login',    [ApiAuthController::class, 'login']);
    Route::post('/register', [ApiAuthController::class, 'register']);
});

// ─────────────────────────────────────────────
// Authenticated routes (Sanctum)
// ─────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',     [ApiAuthController::class, 'logout']);
    Route::post('/auth/logout-all', [ApiAuthController::class, 'logoutAll']);

    // Profile
    Route::get('/profile',   [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    // Notifications
    Route::get('/notifications',                  [ApiNotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',       [ApiNotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',        [ApiNotificationController::class, 'markAllAsRead']);

    // Quick user shortcut (Laravel default)
    Route::get('/user', fn (Request $request) => $request->user());

    // ─────────────────────────────────────────
    // Client endpoints
    // ─────────────────────────────────────────

    Route::prefix('client')->group(function () {
        Route::get('/bookings',                   [ClientBookingController::class, 'index']);
        Route::post('/bookings',                  [ClientBookingController::class, 'store']);
        Route::get('/bookings/{booking}',         [ClientBookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [ClientBookingController::class, 'cancel']);
        Route::get('/bookings/{booking}/eta',     [ClientBookingController::class, 'eta']);
    });

    // ─────────────────────────────────────────
    // Provider endpoints
    // ─────────────────────────────────────────

    // Phase 0 — Mission tracking (existant)
    Route::post('/missions/{mission}/tracking/start',           [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push',    [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop',    [EmployeeMissionTrackingController::class, 'stop']);

    // Phase 11 — Provider presence
    Route::prefix('provider/presence')->group(function () {
        Route::post('/online',    [ProviderPresenceController::class, 'online']);
        Route::post('/offline',   [ProviderPresenceController::class, 'offline']);
        Route::post('/heartbeat', [ProviderPresenceController::class, 'heartbeat']);
        Route::get('/me',         [ProviderPresenceController::class, 'me']);
    });

    // Phase 11 — Mission accept/decline
    Route::prefix('provider/assignments')->group(function () {
        Route::get('/inbox',                 [ProviderMissionAssignmentController::class, 'inbox']);
        Route::get('/{assignment}',          [ProviderMissionAssignmentController::class, 'show']);
        Route::post('/{assignment}/accept',  [ProviderMissionAssignmentController::class, 'accept']);
        Route::post('/{assignment}/decline', [ProviderMissionAssignmentController::class, 'decline']);
    });

    // Phase 12 — Mission lifecycle (start/arrive/complete)
    Route::prefix('provider/missions')->group(function () {
        Route::get('/active',                [ProviderMissionLifecycleController::class, 'active']);
        Route::get('/{mission}',             [ProviderMissionLifecycleController::class, 'show']);
        Route::post('/{mission}/start',      [ProviderMissionLifecycleController::class, 'start']);
        Route::post('/{mission}/arrive',     [ProviderMissionLifecycleController::class, 'arrive']);
        Route::post('/{mission}/complete',   [ProviderMissionLifecycleController::class, 'complete']);
    });
});
```

## 2. config/sanctum.php — durée des tokens

Par défaut, les tokens Sanctum sont éternels. Pour les apps mobiles, c'est OK
(l'app garde le token jusqu'à logout explicite). Mais en cas de besoin de
révocation centralisée :

```php
// config/sanctum.php
'expiration' => null, // null = pas d'expiration (recommandé pour apps mobiles)
// OU
'expiration' => 60 * 24 * 30, // 30 jours en minutes
```

## 3. config/cors.php — autoriser les headers Authorization

Pour que les apps mobiles puissent envoyer le header `Authorization: Bearer <token>` :

```php
// config/cors.php (créer si absent : php artisan vendor:publish --tag=cors)
return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => ['*'],  // En prod : restreindre à votre domaine app
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,  // Sanctum API tokens : pas besoin
];
```

## 4. Bootstrap Sanctum (Laravel 11+)

Vérifier que Sanctum est dans `bootstrap/app.php` :

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
})
```

Si Laravel 10 :

```php
// app/Http/Kernel.php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],
```

## 5. User model — vérifications

Vérifie que `User.php` a bien :

```php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use HasApiTokens;
    // ...
}
```

J'ai vu lors de l'audit que c'est déjà le cas. ✓

## 6. Tester avec curl

Une fois déployé :

```bash
# 1. Login
TOKEN=$(curl -X POST https://ton-app.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"client@test.com","password":"secret"}' \
  | jq -r .token)

# 2. Profile
curl https://ton-app.com/api/profile \
  -H "Authorization: Bearer $TOKEN"

# 3. Liste bookings
curl https://ton-app.com/api/client/bookings \
  -H "Authorization: Bearer $TOKEN"

# 4. Créer un booking ASAP
curl -X POST https://ton-app.com/api/client/bookings \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "service_catalog_id": 1,
    "address": "10 rue de la Paix",
    "city": "Bruxelles",
    "postal_code": "1000",
    "scheduled_date": "2026-05-10",
    "scheduled_time": "14:00",
    "booking_mode": "asap"
  }'

# 5. Cancel
curl -X POST https://ton-app.com/api/client/bookings/123/cancel \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason":"changement de plan"}'

# 6. Logout
curl -X POST https://ton-app.com/api/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

## 7. Tests automatisés

```bash
php artisan test --filter=Phase12Test
```

20 tests doivent passer.

## 8. Documentation API (optionnel)

Pour générer une doc OpenAPI/Swagger automatique, installer scribe :

```bash
composer require knuckleswtf/scribe --dev
php artisan vendor:publish --tag=scribe-config
php artisan scribe:generate
```

Doc accessible sur `/docs` après config.

## 9. Sécurité prod

Avant déploiement prod :

- ✅ HTTPS obligatoire (les tokens passent en clair en HTTP)
- ✅ Rate limiting (déjà configuré dans `routes/api.php` via `throttle:api` middleware par défaut Laravel)
- ✅ `config/cors.php` : restreindre `allowed_origins` à votre domaine app mobile uniquement
- ✅ Vérifier que `APP_DEBUG=false` en prod (sinon les stack traces leakent)
- ⚠ Ne **jamais** logger les tokens dans `storage/logs/`

## 10. Limites Phase 12

Volontairement écartés (pour Phase 13+) :

- **Création booking complexe** : la POST /api/client/bookings est simplifiée.
  Pour le full-flow (zone resolution, pricing dynamique calculé, recurring
  series, options de service, multi-sites entreprise), passer par le composant
  Livewire `PrendreRendezVous` web.
- **ETA précis** : retourne du Haversine simple. Phase 13 ajoutera Google
  Distance Matrix.
- **Stripe Connect endpoints** : payment intents, transfers, payouts, tout ça
  vient en Phase 13.
- **Provider onboarding** : pas d'endpoint `/api/provider/register` — un
  prestataire doit être créé en backoffice. Phase 13+.
- **Multi-langue dans les responses** : les messages d'erreur sont en français.
  Pour internationaliser : enrôler les traductions Phase 9.
- **Refresh tokens** : pas implémenté (Sanctum simple). Pour OAuth2 / refresh
  tokens / device authorization, regarder Laravel Passport.
