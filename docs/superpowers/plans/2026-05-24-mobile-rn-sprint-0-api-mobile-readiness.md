# Brio Mobile RN — Sprint 0 : API Mobile-Readiness

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fermer les 5 gaps API Laravel qui bloquent l'app React Native client — token refresh avec grace period, JSON exception handler unifié, Stripe Connect endpoints provider, helper Reverb mobile auth, et deprecation Capacitor — pour qu'aux Sprints 1+ on consomme une API mobile-clean sans patches en cours de route.

**Architecture:** 100% Laravel backend. Pas de nouvelles tables (les colonnes rotation existent depuis le module API Tokens v2). On ajoute 3 controllers minces, on étend `Handler.php`, on archive Capacitor. TDD pur : test rouge → implémentation minimale → vert → commit.

**Tech Stack:** Laravel 11, Laravel Sanctum, Pest/PHPUnit, Stripe PHP SDK (installé), Pusher/Reverb (config existante).

**Préalables environnement :**
- Branche `feat/mobile-rn-sprint-0` créée depuis `main` (utilise `superpowers:using-git-worktrees` si tu veux un worktree isolé)
- Tests existants passent : `php artisan test --parallel` doit donner ~1472 OK avant de commencer

---

## File Structure

**Will create:**
- `app/Http/Controllers/Api/AuthRefreshController.php`
- `app/Http/Controllers/Api/Provider/StripeConnectController.php`
- `app/Http/Controllers/Api/Realtime/SocketConfigController.php`
- `app/Exceptions/ApiJsonRenderer.php` (helper de format)
- `tests/Feature/Api/AuthRefreshTest.php`
- `tests/Feature/Api/ExceptionHandlerJsonTest.php`
- `tests/Feature/Api/Provider/StripeConnectTest.php`
- `tests/Feature/Api/Realtime/SocketConfigTest.php`
- `docs/realtime-mobile.md`

**Will modify:**
- `app/Exceptions/Handler.php` — ajoute render() avec renderer JSON pour requêtes API
- `routes/api.php` — ajoute routes refresh, stripe-connect, socket-config
- `config/sanctum.php` — vérifier stateful_domains (laisser l'app mobile NE PAS être stateful — utiliser Bearer pur)

**Will remove/archive:**
- `capacitor.config.ts` → supprimer
- `docs/mobile-pwa-capacitor-guide.md` → déplacer vers `docs/archive/`
- `docs/MOBILE_NATIVE_DEPLOYMENT.md` → vérifier si Capacitor-spécifique, archiver si oui

---

## Task 1 — Token refresh avec rotation grace period

**Why:** L'app RN garde un token Sanctum (90j) en `expo-secure-store`. Pour rotation sans déconnecter l'utilisateur sur une erreur réseau pendant le refresh, l'ancien token reste valide ~5 minutes après l'émission du nouveau (rotation grace period). Sans ça, double tap "refresh" en parallèle = 1 token marche, l'autre 401 → user déconnecté.

**Files:**
- Create: `app/Http/Controllers/Api/AuthRefreshController.php`
- Create: `tests/Feature/Api/AuthRefreshTest.php`
- Modify: `routes/api.php`

### Step 1.1 — Vérifier que les colonnes rotation existent

- [ ] Lance la vérif schéma

```bash
php artisan tinker --execute="echo (int) (Schema::hasColumn('personal_access_tokens', 'rotated_from_token_id') && Schema::hasColumn('personal_access_tokens', 'rotation_grace_until'));"
```

Expected output: `1`

Si `0`, écris une migration `database/migrations/2026_05_24_120000_add_rotation_to_personal_access_tokens.php` :

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('personal_access_tokens', 'rotated_from_token_id')) {
                $table->foreignId('rotated_from_token_id')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('personal_access_tokens', 'rotation_grace_until')) {
                $table->timestamp('rotation_grace_until')->nullable()->after('rotated_from_token_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['rotated_from_token_id', 'rotation_grace_until']);
        });
    }
};
```

Puis `php artisan migrate`.

### Step 1.2 — Écrire les tests Feature (rouge)

- [ ] Crée `tests/Feature/Api/AuthRefreshTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthRefreshTest extends TestCase
{
    public function test_refresh_returns_new_token_with_expiry(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $r = $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh');

        $r->assertOk()->assertJsonStructure(['token', 'expires_at']);
        $this->assertNotEquals($old, $r->json('token'));
    }

    public function test_old_token_still_works_during_grace_period(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh')->assertOk();

        // Ancien token toujours valide < 5 min
        $this->withHeader('Authorization', "Bearer {$old}")
            ->getJson('/api/auth/me')->assertOk();
    }

    public function test_old_token_rejected_after_grace_expires(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh')->assertOk();

        $oldRecord = PersonalAccessToken::findToken($old);
        $oldRecord->update(['rotation_grace_until' => now()->subMinute()]);

        $this->withHeader('Authorization', "Bearer {$old}")
            ->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_refresh_requires_auth(): void
    {
        $this->postJson('/api/auth/refresh')->assertUnauthorized();
    }

    public function test_refresh_links_new_token_to_old(): void
    {
        $user = User::factory()->create();
        $old = $user->createToken('mobile')->plainTextToken;
        $oldId = PersonalAccessToken::findToken($old)->id;

        $r = $this->withHeader('Authorization', "Bearer {$old}")
            ->postJson('/api/auth/refresh');

        $newRecord = PersonalAccessToken::findToken($r->json('token'));
        $this->assertEquals($oldId, $newRecord->rotated_from_token_id);
    }
}
```

### Step 1.3 — Lancer les tests pour les voir échouer

- [ ] Run et vérifie l'échec

```bash
php artisan test --filter=AuthRefreshTest
```

Expected: 5 FAIL avec `Route [api/auth/refresh] not defined` ou `404`.

### Step 1.4 — Étendre Sanctum pour respecter la grace period

L'auth Sanctum standard rejette tout token qui n'existe pas. On doit lui apprendre à accepter un token tant que `rotation_grace_until > now()` même s'il a été remplacé.

- [ ] Vérifie le comportement actuel — Sanctum vérifie déjà l'absence d'expiration via `tokenExpired` mais pas la grace. Le plus simple : **NE PAS supprimer** l'ancien token, juste émettre un nouveau et stocker la grace. Sanctum continue de le reconnaître normalement. Aucun changement Sanctum nécessaire.

(Skip cette étape si la logique côté controller suffit — voir 1.5.)

### Step 1.5 — Écrire le controller AuthRefreshController (vert)

- [ ] Crée `app/Http/Controllers/Api/AuthRefreshController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthRefreshController extends Controller
{
    private const GRACE_MINUTES = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldToken = $user->currentAccessToken();

        $deviceName = $oldToken->name ?: 'mobile';
        $newToken = $user->createToken($deviceName);

        // Mark old token as rotated, keep it valid for GRACE_MINUTES
        $oldToken->forceFill([
            'rotated_from_token_id' => null, // it's the source, not derived
            'rotation_grace_until' => now()->addMinutes(self::GRACE_MINUTES),
        ])->save();

        // Link new token back to old
        $newToken->accessToken->forceFill([
            'rotated_from_token_id' => $oldToken->id,
        ])->save();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at?->toIso8601String(),
        ]);
    }
}
```

### Step 1.6 — Étendre la vérification Sanctum pour rejeter l'ancien token après grace

L'ancien token reste dans la table → Sanctum continue de l'accepter même après grace. On doit override via TokenCan ou via un middleware custom. Plus simple : middleware `EnforceTokenGrace` qui s'attache aux routes `auth:sanctum`.

- [ ] Crée `app/Http/Middleware/EnforceTokenGrace.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTokenGrace
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token && $token->rotation_grace_until !== null
            && $token->rotation_grace_until->isPast()) {
            return response()->json([
                'ok' => false,
                'error_code' => 'token_grace_expired',
                'message' => 'Token has been rotated and is no longer valid.',
            ], 401);
        }

        return $next($request);
    }
}
```

- [ ] Enregistre dans `app/Http/Kernel.php` middleware aliases :

```php
protected $middlewareAliases = [
    // ... existing
    'token.grace' => \App\Http\Middleware\EnforceTokenGrace::class,
];
```

### Step 1.7 — Ajouter la route dans routes/api.php

- [ ] Cherche la section auth et ajoute :

```php
Route::middleware(['auth:sanctum', 'token.grace'])->group(function () {
    Route::post('/auth/refresh', \App\Http\Controllers\Api\AuthRefreshController::class)
        ->name('api.auth.refresh');
});
```

Note : si `/api/auth/me` n'existe pas pour les tests, ajoute-le aussi (1-liner) :

```php
Route::middleware(['auth:sanctum', 'token.grace'])->get('/auth/me',
    fn (Request $r) => response()->json(['user' => $r->user()]));
```

### Step 1.8 — Vérifier que tous les tests passent

- [ ] Run

```bash
php artisan test --filter=AuthRefreshTest
```

Expected: 5 PASS.

Si un test échoue : lit le message d'erreur, débugge l'attendu vs. le réel. Ne change PAS les tests pour les faire passer — corrige le code.

### Step 1.9 — Tester aussi que les routes existantes ne sont pas cassées

- [ ] Run le sous-ensemble auth

```bash
php artisan test --filter="Auth|Sanctum|Token"
```

Expected: tous PASS (aucune régression).

### Step 1.10 — Commit

- [ ] Stage et commit

```bash
git add app/Http/Controllers/Api/AuthRefreshController.php \
        app/Http/Middleware/EnforceTokenGrace.php \
        app/Http/Kernel.php \
        routes/api.php \
        tests/Feature/Api/AuthRefreshTest.php
# Si tu as créé la migration, ajoute-la aussi
git commit -m "feat(api): add /api/auth/refresh with rotation grace period

Issues a new Sanctum token while keeping the old one valid for 5 minutes,
preventing logout on network errors during mobile token rotation.
Enforced via EnforceTokenGrace middleware."
```

---

## Task 2 — Exception handler JSON unifié pour l'API

**Why:** Sans ça, le mobile reçoit des shapes incohérentes : 422 = `{message, errors}` (Laravel défaut), 401 = `{message}`, 429 = blob HTML, 500 = HTML stack trace en dev. UI mobile devient un nid d'edge cases. On veut **un seul format** pour toutes les erreurs API : `{ok: false, error_code, message, errors?}`.

**Files:**
- Create: `app/Exceptions/ApiJsonRenderer.php`
- Modify: `app/Exceptions/Handler.php`
- Create: `tests/Feature/Api/ExceptionHandlerJsonTest.php`

### Step 2.1 — Écrire les tests (rouge)

- [ ] Crée `tests/Feature/Api/ExceptionHandlerJsonTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\TestCase;

class ExceptionHandlerJsonTest extends TestCase
{
    public function test_validation_error_returns_unified_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $r = $this->postJson('/api/client/bookings', []); // expect required fields

        $r->assertStatus(422)
          ->assertJson([
              'ok' => false,
              'error_code' => 'validation_failed',
          ])
          ->assertJsonStructure(['ok', 'error_code', 'message', 'errors']);
    }

    public function test_unauthenticated_returns_unified_shape(): void
    {
        $r = $this->getJson('/api/auth/me');

        $r->assertStatus(401)
          ->assertJson([
              'ok' => false,
              'error_code' => 'unauthenticated',
          ])
          ->assertJsonStructure(['ok', 'error_code', 'message']);
    }

    public function test_not_found_returns_unified_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $r = $this->getJson('/api/client/bookings/99999999');

        $r->assertStatus(404)
          ->assertJson([
              'ok' => false,
              'error_code' => 'not_found',
          ]);
    }

    public function test_throttled_returns_unified_shape(): void
    {
        for ($i = 0; $i < 70; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@x.x', 'password' => 'x']);
        }
        $r = $this->postJson('/api/auth/login', ['email' => 'x@x.x', 'password' => 'x']);

        $r->assertStatus(429)
          ->assertJson(['ok' => false, 'error_code' => 'rate_limited']);
    }

    public function test_server_error_returns_unified_shape_in_production(): void
    {
        config(['app.debug' => false]);

        $this->app->router->get('/api/boom', fn () => throw new \RuntimeException('boom'));

        $r = $this->getJson('/api/boom');

        $r->assertStatus(500)
          ->assertJson([
              'ok' => false,
              'error_code' => 'server_error',
          ])
          ->assertJsonMissing(['boom']); // no leaked details in prod
    }

    public function test_html_request_keeps_default_behavior(): void
    {
        // Non-API request still gets Laravel default error pages
        $r = $this->get('/non-existent');
        $r->assertStatus(404);
        $this->assertStringContainsString('<', $r->getContent()); // HTML not JSON
    }
}
```

N'oublie pas d'importer `Laravel\Sanctum\Sanctum` en haut du fichier.

### Step 2.2 — Lancer pour échouer

- [ ] Run

```bash
php artisan test --filter=ExceptionHandlerJsonTest
```

Expected: 6 FAIL (shapes par défaut Laravel ne matchent pas).

### Step 2.3 — Écrire le helper de rendu (vert)

- [ ] Crée `app/Exceptions/ApiJsonRenderer.php`

```php
<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiJsonRenderer
{
    public static function render(Request $request, Throwable $e): ?JsonResponse
    {
        if (!self::shouldRender($request)) {
            return null;
        }

        [$status, $code, $message, $errors] = self::resolve($e);

        $payload = [
            'ok' => false,
            'error_code' => $code,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        if (config('app.debug') && $status >= 500) {
            $payload['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ];
        }

        return response()->json($payload, $status);
    }

    private static function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private static function resolve(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [422, 'validation_failed', $e->getMessage(), $e->errors()];
        }

        if ($e instanceof AuthenticationException) {
            return [401, 'unauthenticated', 'Authentication required.', null];
        }

        if ($e instanceof AuthorizationException) {
            return [403, 'forbidden', $e->getMessage() ?: 'Forbidden.', null];
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return [404, 'not_found', 'Resource not found.', null];
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return [429, 'rate_limited', 'Too many requests.', null];
        }

        if ($e instanceof HttpException) {
            return [
                $e->getStatusCode(),
                'http_error',
                $e->getMessage() ?: 'HTTP error.',
                null,
            ];
        }

        return [500, 'server_error', 'An unexpected error occurred.', null];
    }
}
```

### Step 2.4 — Brancher dans Handler.php

- [ ] Lis `app/Exceptions/Handler.php` pour voir la structure actuelle

```bash
# Run mentally — utilise Read tool
```

- [ ] Ajoute la méthode `render` (ou étends-la) :

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    // ... existing properties ($dontReport, $dontFlash, etc.)

    public function render($request, Throwable $e)
    {
        if ($json = ApiJsonRenderer::render($request, $e)) {
            return $json;
        }

        return parent::render($request, $e);
    }

    // ... existing register() if present
}
```

Si le projet est en Laravel 11 avec `bootstrap/app.php` (nouveau style), branche plutôt là :

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        return \App\Exceptions\ApiJsonRenderer::render($request, $e);
    });
})
```

Vérifie quel style le projet utilise avant de coder.

### Step 2.5 — Lancer les tests, voir vert

- [ ] Run

```bash
php artisan test --filter=ExceptionHandlerJsonTest
```

Expected: 6 PASS.

### Step 2.6 — Vérifier aucune régression sur la suite complète

- [ ] Run l'intégralité

```bash
php artisan test --parallel
```

Expected: ~1472 + 5 (refresh) + 6 (handler) = ~1483 PASS. Si des tests web cassent (ex. tests qui attendaient la HTML page 404 et reçoivent JSON), c'est que `shouldRender` est trop laxiste — restreins-le à `$request->is('api/*')` strict.

### Step 2.7 — Commit

- [ ] Stage et commit

```bash
git add app/Exceptions/ApiJsonRenderer.php \
        app/Exceptions/Handler.php \
        tests/Feature/Api/ExceptionHandlerJsonTest.php
# Si Laravel 11 style : ajoute aussi bootstrap/app.php
git commit -m "feat(api): unified JSON error shape for API requests

All /api/* errors return {ok:false, error_code, message, errors?} so the
React Native client can pattern-match on error_code instead of HTTP-status
edge cases. Validation/auth/auth-z/404/429/500 covered."
```

---

## Task 3 — Stripe Connect endpoints provider

**Why:** Phase 2 (Provider RN) consommera Stripe Connect pour onboarder les providers (Express account) et leur permettre de voir leurs payouts. Même si Phase 1 = client uniquement, on couvre ces endpoints dans Sprint 0 pour éviter un Sprint 0bis en Phase 2.

**Files:**
- Create: `app/Http/Controllers/Api/Provider/StripeConnectController.php`
- Create: `tests/Feature/Api/Provider/StripeConnectTest.php`
- Modify: `routes/api.php`

### Step 3.1 — Vérifier le service Stripe Connect existant

- [ ] Cherche le service

```bash
# Utilise Grep tool avec pattern "StripeConnect" et glob "**/*.php"
```

Si un `StripeConnectService` ou équivalent existe (cf. module Stripe v2 en mémoire), réutilise-le. Sinon, la logique va dans le controller pour Sprint 0.

### Step 3.2 — Écrire les tests (rouge)

- [ ] Crée `tests/Feature/Api/Provider/StripeConnectTest.php`

```php
<?php

namespace Tests\Feature\Api\Provider;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeConnectTest extends TestCase
{
    public function test_status_returns_not_onboarded_for_new_provider(): void
    {
        $provider = User::factory()->create(['role' => 'provider']);
        Sanctum::actingAs($provider);

        $r = $this->getJson('/api/provider/stripe-connect/status');

        $r->assertOk()->assertJson([
            'onboarded' => false,
            'charges_enabled' => false,
            'payouts_enabled' => false,
        ]);
    }

    public function test_onboard_returns_account_link_url(): void
    {
        $this->markTestSkipped('Requires Stripe test mode key — enable when STRIPE_SECRET_TEST set.');

        $provider = User::factory()->create(['role' => 'provider']);
        Sanctum::actingAs($provider);

        $r = $this->postJson('/api/provider/stripe-connect/onboard');

        $r->assertOk()->assertJsonStructure(['url', 'expires_at']);
        $this->assertStringStartsWith('https://connect.stripe.com/', $r->json('url'));
    }

    public function test_payouts_returns_paginated_list(): void
    {
        $provider = User::factory()->create([
            'role' => 'provider',
            'stripe_account_id' => 'acct_test_123',
        ]);
        Sanctum::actingAs($provider);

        // Mock the Stripe call — utilise Stripe's mock or a fake service
        $this->mock(\App\Services\Stripe\StripeConnectService::class, function ($mock) {
            $mock->shouldReceive('listPayouts')->andReturn([
                'data' => [],
                'has_more' => false,
            ]);
        });

        $r = $this->getJson('/api/provider/stripe-connect/payouts');

        $r->assertOk()->assertJsonStructure(['data', 'has_more']);
    }

    public function test_endpoints_require_provider_role(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $this->getJson('/api/provider/stripe-connect/status')->assertForbidden();
    }
}
```

### Step 3.3 — Voir échouer

- [ ] Run

```bash
php artisan test --filter=StripeConnectTest
```

Expected: 4 FAIL (404 / route non trouvée).

### Step 3.4 — Implémenter le controller (vert)

- [ ] Crée `app/Http/Controllers/Api/Provider/StripeConnectController.php`

```php
<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    public function __construct(private StripeConnectService $stripe) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_account_id) {
            return response()->json([
                'onboarded' => false,
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'requirements' => [],
            ]);
        }

        $account = $this->stripe->retrieveAccount($user->stripe_account_id);

        return response()->json([
            'onboarded' => true,
            'charges_enabled' => $account->charges_enabled,
            'payouts_enabled' => $account->payouts_enabled,
            'requirements' => $account->requirements?->currently_due ?? [],
        ]);
    }

    public function onboard(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_account_id) {
            $accountId = $this->stripe->createExpressAccount($user);
            $user->update(['stripe_account_id' => $accountId]);
        }

        $link = $this->stripe->createAccountLink(
            $user->stripe_account_id,
            refreshUrl: config('app.url') . '/dashboard/employe/stripe-onboard/refresh',
            returnUrl: config('app.url') . '/dashboard/employe/stripe-onboard/return',
        );

        return response()->json([
            'url' => $link->url,
            'expires_at' => date('c', $link->expires_at),
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->stripe_account_id) {
            return response()->json(['data' => [], 'has_more' => false]);
        }

        $limit = min((int) $request->query('limit', 25), 100);
        $startingAfter = $request->query('starting_after');

        $payouts = $this->stripe->listPayouts(
            $user->stripe_account_id,
            $limit,
            $startingAfter,
        );

        return response()->json([
            'data' => $payouts->data,
            'has_more' => $payouts->has_more,
        ]);
    }

    public function dashboardLink(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->stripe_account_id, 404, 'No Stripe account');

        $link = $this->stripe->createLoginLink($user->stripe_account_id);

        return response()->json(['url' => $link->url]);
    }
}
```

### Step 3.5 — Étendre/Créer StripeConnectService si absent

- [ ] Si `app/Services/Stripe/StripeConnectService.php` n'existe pas, crée-le minimal :

```php
<?php

namespace App\Services\Stripe;

use App\Models\User;
use Stripe\StripeClient;

class StripeConnectService
{
    public function __construct(private StripeClient $stripe) {}

    public function retrieveAccount(string $accountId): \Stripe\Account
    {
        return $this->stripe->accounts->retrieve($accountId);
    }

    public function createExpressAccount(User $user): string
    {
        $account = $this->stripe->accounts->create([
            'type' => 'express',
            'country' => $user->country_code ?? 'FR',
            'email' => $user->email,
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);

        return $account->id;
    }

    public function createAccountLink(string $accountId, string $refreshUrl, string $returnUrl): \Stripe\AccountLink
    {
        return $this->stripe->accountLinks->create([
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
    }

    public function listPayouts(string $accountId, int $limit, ?string $startingAfter): \Stripe\Collection
    {
        $params = ['limit' => $limit];
        if ($startingAfter) {
            $params['starting_after'] = $startingAfter;
        }

        return $this->stripe->payouts->all($params, ['stripe_account' => $accountId]);
    }

    public function createLoginLink(string $accountId): \Stripe\LoginLink
    {
        return $this->stripe->accounts->createLoginLink($accountId);
    }
}
```

S'il existe déjà (Stripe v2 module), vérifie qu'il a ces 5 méthodes et ajoute les manquantes.

### Step 3.6 — Routes

- [ ] Ajoute dans `routes/api.php`

```php
Route::middleware(['auth:sanctum', 'token.grace', 'role:provider'])
    ->prefix('provider/stripe-connect')
    ->group(function () {
        Route::get('/status', [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'status']);
        Route::post('/onboard', [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'onboard']);
        Route::get('/payouts', [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'payouts']);
        Route::get('/dashboard-link', [\App\Http\Controllers\Api\Provider\StripeConnectController::class, 'dashboardLink']);
    });
```

Vérifie le middleware `role:` — selon le projet, c'est peut-être `EnsureUserHasRole` ou autre. Cherche un usage existant.

### Step 3.7 — Vert

- [ ] Run

```bash
php artisan test --filter=StripeConnectTest
```

Expected: 4 PASS (le onboard test reste skipped si pas de clé Stripe test).

### Step 3.8 — Commit

```bash
git add app/Http/Controllers/Api/Provider/StripeConnectController.php \
        app/Services/Stripe/StripeConnectService.php \
        routes/api.php \
        tests/Feature/Api/Provider/StripeConnectTest.php
git commit -m "feat(api): add provider Stripe Connect endpoints

GET /api/provider/stripe-connect/status: onboarding state
POST /api/provider/stripe-connect/onboard: returns account link URL
GET /api/provider/stripe-connect/payouts: paginated payouts list
GET /api/provider/stripe-connect/dashboard-link: Stripe Express dashboard

Will be consumed by RN provider app in Phase 2."
```

---

## Task 4 — Helper endpoint pour Reverb mobile auth

**Why:** L'app RN doit savoir où se connecter en WebSocket (host, port, scheme, app key public) et comment s'authentifier sur les channels privés (POST `/broadcasting/auth` avec Bearer token). Aujourd'hui, ces infos sont dans `config/broadcasting.php` côté serveur — le client mobile ne peut pas les deviner. On ajoute `/api/realtime/socket-config` qui retourne la config publique pour Pusher/Reverb client mobile.

**Files:**
- Create: `app/Http/Controllers/Api/Realtime/SocketConfigController.php`
- Create: `tests/Feature/Api/Realtime/SocketConfigTest.php`
- Modify: `routes/api.php`
- Create: `docs/realtime-mobile.md`

### Step 4.1 — Test (rouge)

- [ ] Crée `tests/Feature/Api/Realtime/SocketConfigTest.php`

```php
<?php

namespace Tests\Feature\Api\Realtime;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SocketConfigTest extends TestCase
{
    public function test_returns_socket_config_for_authenticated_user(): void
    {
        config([
            'broadcasting.connections.reverb.key' => 'pk_test_123',
            'broadcasting.connections.reverb.options.host' => 'realtime.brio.test',
            'broadcasting.connections.reverb.options.port' => 443,
            'broadcasting.connections.reverb.options.scheme' => 'https',
        ]);
        config(['broadcasting.default' => 'reverb']);

        Sanctum::actingAs(User::factory()->create());

        $r = $this->getJson('/api/realtime/socket-config');

        $r->assertOk()->assertJson([
            'driver' => 'reverb',
            'key' => 'pk_test_123',
            'host' => 'realtime.brio.test',
            'port' => 443,
            'scheme' => 'https',
            'auth_endpoint' => '/api/broadcasting/auth',
        ]);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/realtime/socket-config')->assertUnauthorized();
    }

    public function test_does_not_leak_secret(): void
    {
        config(['broadcasting.connections.reverb.secret' => 'sk_super_secret']);

        Sanctum::actingAs(User::factory()->create());
        $r = $this->getJson('/api/realtime/socket-config');

        $this->assertStringNotContainsString('sk_super_secret', $r->getContent());
    }
}
```

### Step 4.2 — Voir échouer

```bash
php artisan test --filter=SocketConfigTest
```

Expected: 3 FAIL.

### Step 4.3 — Implémenter (vert)

- [ ] Crée `app/Http/Controllers/Api/Realtime/SocketConfigController.php`

```php
<?php

namespace App\Http\Controllers\Api\Realtime;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SocketConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $driver = config('broadcasting.default');
        $cfg = config("broadcasting.connections.{$driver}", []);

        return response()->json([
            'driver' => $driver,
            'key' => $cfg['key'] ?? null,
            'host' => $cfg['options']['host'] ?? null,
            'port' => $cfg['options']['port'] ?? null,
            'scheme' => $cfg['options']['scheme'] ?? 'https',
            'auth_endpoint' => '/api/broadcasting/auth',
        ]);
    }
}
```

### Step 4.4 — Route

- [ ] Dans `routes/api.php` :

```php
Route::middleware(['auth:sanctum', 'token.grace'])
    ->get('/realtime/socket-config', \App\Http\Controllers\Api\Realtime\SocketConfigController::class);
```

### Step 4.5 — Vérifier que `/api/broadcasting/auth` existe et accepte Bearer

- [ ] Cherche dans `routes/web.php` et `routes/api.php`

```bash
# Grep "broadcasting/auth"
```

Si la route est dans `routes/web.php` (default Laravel), elle accepte les cookies de session — pas Bearer. Pour le mobile, déplace-la (ou duplique) sur `routes/api.php` :

```php
Route::middleware('auth:sanctum')
    ->post('/broadcasting/auth', [\Illuminate\Broadcasting\BroadcastController::class, 'authenticate']);
```

Ajoute un test rapide :

```php
public function test_broadcasting_auth_accepts_bearer_token(): void
{
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $r = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-user.' . $user->id,
        ]);

    $r->assertOk()->assertJsonStructure(['auth']);
}
```

### Step 4.6 — Vert

```bash
php artisan test --filter=SocketConfigTest
```

Expected: 3-4 PASS.

### Step 4.7 — Docs : flow WebSocket pour mobile

- [ ] Crée `docs/realtime-mobile.md`

```markdown
# Reverb / WebSocket — Mobile RN integration

## Discovery

L'app RN appelle d'abord `GET /api/realtime/socket-config` (Bearer Sanctum) pour récupérer :

```json
{
  "driver": "reverb",
  "key": "pk_xxx",
  "host": "realtime.brio.com",
  "port": 443,
  "scheme": "https",
  "auth_endpoint": "/api/broadcasting/auth"
}
```

## Côté client (Pusher JS)

```typescript
import Pusher from 'pusher-js/react-native';

const pusher = new Pusher(cfg.key, {
  wsHost: cfg.host,
  wsPort: cfg.port,
  forceTLS: cfg.scheme === 'https',
  cluster: '', // not used with Reverb
  authorizer: (channel) => ({
    authorize: async (socketId, callback) => {
      const r = await fetch(`${API_BASE}${cfg.auth_endpoint}`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channel.name,
        }),
      });
      const data = await r.json();
      callback(null, data);
    },
  }),
});

const channel = pusher.subscribe(`private-mission.${missionId}`);
channel.bind('mission.position.updated', (event) => { /* ... */ });
```

## Channels disponibles

- `private-mission.{missionId}` — events MissionPositionUpdated, MissionStatusUpdated, MissionLiveEta
- `private-user.{userId}` — UserLiveNotification (chat, push fallback, system)
- `private-channel.{channelId}` — Chat v2 messages (ChatMessageSentEvent)
- `presence-org.{orgId}` — présence membres d'une org
- `presence-team.{teamId}` — présence équipe terrain
- `providers.presence` — réservé admin/dispatcher (refusé pour mobile)

## Reconnexion

Pusher JS gère reconnexion auto. Côté UI : afficher un badge "Reconnecting…" si `pusher.connection.state === 'connecting'` pendant > 3s.
```

### Step 4.8 — Commit

```bash
git add app/Http/Controllers/Api/Realtime/SocketConfigController.php \
        routes/api.php \
        tests/Feature/Api/Realtime/SocketConfigTest.php \
        docs/realtime-mobile.md
git commit -m "feat(api): add /api/realtime/socket-config + Bearer broadcasting/auth

Mobile clients can now discover Reverb host/port/key via API and authenticate
private channels using their Sanctum Bearer token instead of session cookies.
Docs in docs/realtime-mobile.md show the Pusher-JS integration pattern."
```

---

## Task 5 — Deprecation Capacitor

**Why:** On a décidé de remplacer Capacitor par React Native. Garder `capacitor.config.ts` au repo crée de l'ambiguïté pour les futurs développeurs. On archive proprement avec une note CHANGELOG.

**Files:**
- Remove: `capacitor.config.ts`
- Move: `docs/mobile-pwa-capacitor-guide.md` → `docs/archive/mobile-pwa-capacitor-guide.md`
- Move (peut-être): `docs/MOBILE_NATIVE_DEPLOYMENT.md` → `docs/archive/` si Capacitor-spécifique
- Create: `docs/archive/README.md` (si dossier nouveau)
- Modify: `package.json` (retirer scripts capacitor s'il y en a)
- Modify: `CHANGELOG.md` ou créer `docs/decisions/2026-05-24-rn-replaces-capacitor.md`

### Step 5.1 — Vérifier ce que Capacitor touche

- [ ] Cherche les références

```bash
# Grep "capacitor" --type js --type json --type ts --type php --type md
```

Note tous les fichiers qui en parlent.

### Step 5.2 — Vérifier les scripts package.json

- [ ] Lis `package.json`

Note les scripts `cap:*` (sync, copy, build, run) → à supprimer.
Note les dépendances `@capacitor/*` → à retirer.

### Step 5.3 — Archive doc

- [ ] Crée le dossier d'archive

```bash
mkdir -p docs/archive
```

- [ ] Déplace les docs Capacitor

```bash
git mv docs/mobile-pwa-capacitor-guide.md docs/archive/
# Vérifie MOBILE_NATIVE_DEPLOYMENT.md :
grep -i capacitor docs/MOBILE_NATIVE_DEPLOYMENT.md && git mv docs/MOBILE_NATIVE_DEPLOYMENT.md docs/archive/
```

- [ ] Crée `docs/archive/README.md` si nouveau

```markdown
# Archived documentation

Documentation pour des approches techniques qu'on a abandonnées. Conservé pour traçabilité historique. **Ne pas suivre ces guides** pour du nouveau code.

- `mobile-pwa-capacitor-guide.md` — remplacé par React Native/Expo (décision 2026-05-24)
- `MOBILE_NATIVE_DEPLOYMENT.md` — remplacé par EAS Build/Submit (décision 2026-05-24)
```

### Step 5.4 — Décision documentée

- [ ] Crée `docs/decisions/2026-05-24-rn-replaces-capacitor.md` (ADR léger)

```markdown
# ADR — React Native (Expo) remplace Capacitor pour le mobile

**Date:** 2026-05-24
**Statut:** Accepté
**Auteur:** m-u-s-s

## Contexte

Brio avait une config Capacitor (livrée sprint 0-9, 2026-05-20) + un Client Mobile POC V2 Vue 3 islands hybride Livewire (livré 2026-05-23). On souhaite à présent shipper une app mobile native sur les stores avec une UX fluide pour le client (booking, tracking, paiement) et plus tard pour le provider (terrain).

## Décision

Remplacer Capacitor par React Native via Expo SDK + EAS Build/Submit. Monorepo `/mobile/{client|provider}`. Admin reste 100% web Livewire.

## Conséquences

**Positives :**
- Perf native (60fps gestures, carte, listes virtualisées)
- Accès APIs natives plus large (push FCM/APNs, geoloc background, camera, biométrie)
- Review stores plus simple (vraie app vs webview)
- Écosystème RN mature (~7 ans) + Expo OTA updates

**Négatives :**
- Codebase mobile dédiée à maintenir en parallèle du web
- Le Client Mobile POC V2 Vue (33 commits) ne se porte pas — UI à refaire en RN
- Risk de drift versions API entre web (Livewire) et mobile (RN) — mitigation : tests E2E API + OpenAPI spec

## Alternatives considérées

- **Garder Capacitor** : webview reste limité (gestures, perf, accès natif) + review stores moins favorable.
- **Flutter** : écosystème plus jeune, équipe pas formée Dart.
- **PWA seule** : Apple notifications + install flow trop limités.

## Migration

Voir `docs/superpowers/plans/2026-05-24-mobile-rn-phase1-master-index.md`.
```

### Step 5.5 — Supprimer capacitor.config.ts

- [ ] Supprime

```bash
git rm capacitor.config.ts
```

### Step 5.6 — Nettoyer package.json

- [ ] Lis `package.json`, retire les scripts `cap:*` (ex. `cap:sync`, `cap:copy`, `cap:build`, `cap:run:ios`, `cap:run:android`)

- [ ] Retire `@capacitor/*` de `dependencies` et `devDependencies` s'il y en a

- [ ] Run `npm install` (ou `pnpm install`) pour mettre à jour le lockfile

```bash
npm install
```

Note : la mémoire dit "Capacitor SDK NON listé" — il se peut qu'il n'y ait rien à retirer côté package.json. Vérifie quand même.

### Step 5.7 — Commit

```bash
git add docs/archive/ docs/decisions/2026-05-24-rn-replaces-capacitor.md \
        package.json package-lock.json
git rm capacitor.config.ts docs/mobile-pwa-capacitor-guide.md docs/MOBILE_NATIVE_DEPLOYMENT.md 2>/dev/null || true
git commit -m "chore(mobile): archive Capacitor, document RN/Expo replacement

ADR docs/decisions/2026-05-24-rn-replaces-capacitor.md.
Capacitor docs moved to docs/archive/. capacitor.config.ts removed.
No package.json deps to clean (Capacitor SDK was never installed)."
```

---

## Vérification finale Sprint 0

### Step F.1 — Run suite complète

- [ ] Run

```bash
php artisan test --parallel
```

Expected: **~1490 PASS** (1472 baseline + 5 refresh + 6 handler + 4 stripe connect + 3-4 socket config + 0 régression). Si régression : fix avant merge.

### Step F.2 — Lint / static analysis

- [ ] Run le linter du projet

```bash
# Le projet utilise pint, phpstan ?
composer pint -- --test 2>/dev/null && composer phpstan 2>/dev/null
# Si scripts custom :
composer run-script test
```

Fix tout warning critique.

### Step F.3 — Smoke test manuel curl

- [ ] Login → refresh → call protected endpoint

```bash
# Démarre le serveur
php artisan serve &
SERVER_PID=$!

# Login
TOKEN=$(curl -sX POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@brio.dev","password":"password"}' | jq -r '.token')

# Refresh
NEW_TOKEN=$(curl -sX POST http://127.0.0.1:8000/api/auth/refresh \
  -H "Authorization: Bearer $TOKEN" | jq -r '.token')

echo "Old: $TOKEN"
echo "New: $NEW_TOKEN"

# Socket config
curl -s http://127.0.0.1:8000/api/realtime/socket-config \
  -H "Authorization: Bearer $NEW_TOKEN" | jq .

# Trigger validation error
curl -s -X POST http://127.0.0.1:8000/api/client/bookings \
  -H "Authorization: Bearer $NEW_TOKEN" \
  -H "Content-Type: application/json" -d '{}' | jq .

kill $SERVER_PID
```

Expected shapes : `{token, expires_at}`, `{driver, key, host, port, scheme, auth_endpoint}`, `{ok:false, error_code:"validation_failed", message, errors:{...}}`.

### Step F.4 — Mettre à jour le master index

- [ ] Edit `docs/superpowers/plans/2026-05-24-mobile-rn-phase1-master-index.md` : passe Sprint 0 statut à `✅ Mergé YYYY-MM-DD` et débloque Sprint 1.

### Step F.5 — PR

- [ ] Crée la PR

```bash
git push -u origin feat/mobile-rn-sprint-0
gh pr create --title "Sprint 0 — API mobile-readiness (token refresh, JSON errors, Stripe Connect, Reverb mobile auth, Capacitor archive)" \
  --body "$(cat <<'EOF'
## Summary
- POST /api/auth/refresh with 5-min rotation grace period (token.grace middleware)
- Unified JSON error shape for /api/* requests via ApiJsonRenderer
- Provider Stripe Connect endpoints (status/onboard/payouts/dashboard-link)
- /api/realtime/socket-config + Bearer-friendly /api/broadcasting/auth
- Capacitor archived, RN/Expo decision documented

## Why
Bloquant pour démarrer Sprint 1 (Monorepo + Expo bootstrap) de la Phase 1 React Native client. Voir master index `docs/superpowers/plans/2026-05-24-mobile-rn-phase1-master-index.md`.

## Test plan
- [x] AuthRefreshTest (5 tests)
- [x] ExceptionHandlerJsonTest (6 tests)
- [x] StripeConnectTest (4 tests, onboard skipped sans Stripe test key)
- [x] SocketConfigTest (3-4 tests)
- [x] Full suite passe (~1490 OK)
- [x] Smoke test manuel curl
EOF
)"
```

---

## Self-Review

### Spec coverage

| Gap initial | Task | Status |
|---|---|---|
| Token refresh rotation grace logic | Task 1 | ✅ |
| Exception Handler API unifié | Task 2 | ✅ |
| Stripe Connect endpoints | Task 3 | ✅ |
| Reverb mobile auth flow | Task 4 | ✅ |
| Capacitor deprecation | Task 5 | ✅ |

### Placeholder scan

Aucun "TBD", "TODO", "implement later", "Similar to Task N". Tous les blocs code sont concrets. Les `assertJson(...)` matchent les payloads définis dans les controllers.

### Type consistency

- `error_code` (snake_case) utilisé partout côté JSON ✅
- `rotated_from_token_id` / `rotation_grace_until` ✅ (mêmes noms partout)
- `StripeConnectService` méthodes : `retrieveAccount`, `createExpressAccount`, `createAccountLink`, `listPayouts`, `createLoginLink` ✅ (mêmes signatures dans controller + service)
- `EnforceTokenGrace` middleware alias `token.grace` ✅ utilisé dans 2 endroits identiques
- `/api/auth/me` ajouté dans Task 1 → utilisé dans Task 2 test `test_unauthenticated_returns_unified_shape` ✅

### Risques résiduels

- Si `routes/web.php` a déjà `/broadcasting/auth` enregistré via `Broadcast::routes()`, l'ajout dans `routes/api.php` peut créer un doublon. **À vérifier au Step 4.5** — supprime le `Broadcast::routes()` si besoin et garde uniquement la version `auth:sanctum` Bearer.
- Le projet utilise peut-être Laravel 11 style (`bootstrap/app.php`) au lieu du `Handler.php` legacy. Step 2.4 traite les deux cas mais demande vérification visuelle.
- Stripe Connect tests "onboard" et "payouts" mocks dépendent de comment le projet teste Stripe ailleurs. Cherche `Mockery::mock(StripeClient::class)` ou similaire dans `tests/` avant de coder Task 3.

---

## Handoff exécution

Plan prêt. Pour exécuter via subagent-driven-development :
1. Dispatch implementer subagent sur Task 1 (Auth refresh) avec le texte complet
2. Spec review → Quality review → next task
3. Continuer Task 2 → 3 → 4 → 5
4. Final review → PR
