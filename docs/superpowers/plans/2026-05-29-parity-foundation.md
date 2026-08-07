# Parity Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every Brio module reachable on mobile (native where it exists, embedded-web otherwise) after a single native login, with the web becoming a full mobile-capable PWA — without rebuilding ~150 screens.

**Architecture:** A Sanctum→web-session auth bridge issues short-lived single-use handoff tickets; a `react-native-webview` host renders any existing web page (chrome-stripped via an embed flag) inside the Expo apps; a config-driven parity registry maps each module to its mobile delivery mode and drives navigation, so a future native migration is a one-line flag flip.

**Tech Stack:** Laravel 10 (Sanctum, Blade/Livewire, Cache), PHPUnit; React Native / Expo (TypeScript), `@brio/shared` workspace, axios `apiClient`, Jest + `@testing-library/react-native`, `react-native-webview`.

**Spec:** `docs/superpowers/specs/2026-05-29-parity-foundation-design.md`
**Branch:** `feat/parity-foundation` (already created)

---

## File structure

**Backend (Laravel)**
- Create `app/Services/WebView/WebViewTicketService.php` — issue/redeem opaque single-use tickets (cache-backed). One responsibility: ticket lifecycle.
- Create `app/Http/Controllers/Api/Auth/WebViewAuthController.php` — `POST /api/auth/webview-ticket` (Sanctum). Thin.
- Create `app/Http/Controllers/WebViewEntryController.php` — `GET /m/enter` web handoff (session). Thin.
- Create `resources/views/webview/session-expired.blade.php` — tiny page that posts `sessionExpired` to the bridge.
- Create `app/Http/Middleware/EmbedMode.php` — shares `$embedded` flag with views.
- Modify `app/Http/Kernel.php:74` — add `EmbedMode` to the `web` group.
- Modify `resources/views/layouts/app.blade.php` — guard chrome behind `$embedded`.
- Create `config/parity.php` — module → delivery-mode registry.
- Create `app/Http/Controllers/Api/ParityMapController.php` — `GET /api/parity-map` (Sanctum, role-filtered).
- Modify `routes/api/auth.php`, `routes/web.php`, `routes/api/client.php` — register routes.
- Create tests: `tests/Unit/WebView/WebViewTicketServiceTest.php`, `tests/Feature/WebView/WebViewHandoffTest.php`, `tests/Feature/WebView/EmbedModeTest.php`, `tests/Feature/WebView/ParityMapTest.php`, `tests/Feature/WebView/PwaManifestTest.php`.

**Mobile shared (`mobile/shared/src`)**
- Modify `config/env.ts` — add `webUrl`.
- Create `webview/bridge.ts` — native↔web message protocol + injected JS.
- Create `webview/useWebViewTicket.ts` — `fetchWebViewUrl`.
- Create `webview/EmbeddedModuleScreen.tsx` — the WebView host.
- Create `parity/useParityMap.ts` — `fetchParityMap` (+ AsyncStorage cache).
- Modify `index.ts` — export the above.
- Tests co-located in `webview/__tests__/` and `parity/__tests__/`.

**Mobile client (`mobile/client`)**
- Create `__mocks__/react-native-webview.tsx` — Jest mock.
- Modify `jest.config.ts` + `tsconfig.json` — map `@/webview`, `@/parity`, mock webview.
- Create `src/screens/EmbeddedModuleRoute.tsx` — route wrapper wiring shared host to navigation.
- Create `src/screens/ModuleHubScreen.tsx` — parity-driven module list.
- Modify `src/navigation/RootNavigator.tsx` + `src/navigation/types.ts` — register `EmbeddedModule`.
- Tests in `src/screens/__tests__/`.

---

## Task 1: WebViewTicketService

**Files:**
- Create: `app/Services/WebView/WebViewTicketService.php`
- Test: `tests/Unit/WebView/WebViewTicketServiceTest.php`

Design note: the spec called for an HMAC-signed ticket. Single-use enforcement requires server state regardless, so we use an **opaque cryptographically-random token stored server-side in the cache** (its SHA-256 is the cache key) and consume it atomically via `Cache::pull`. This is strictly stronger than a stateless HMAC for replay protection and simpler. Binding (user, token id, device) is stored in the payload.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\WebView;

use App\Models\User;
use App\Services\WebView\WebViewTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebViewTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WebViewTicketService
    {
        return app(WebViewTicketService::class);
    }

    public function test_issue_then_redeem_returns_bound_payload(): void
    {
        $user = User::factory()->create();
        $svc = $this->service();

        $ticket = $svc->issue($user, 'device-abc', '/dashboard');
        $payload = $svc->redeem($ticket);

        $this->assertNotNull($payload);
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame('device-abc', $payload['device_id']);
        $this->assertSame('/dashboard', $payload['target_path']);
    }

    public function test_ticket_is_single_use(): void
    {
        $user = User::factory()->create();
        $svc = $this->service();

        $ticket = $svc->issue($user, 'd', '/x');
        $this->assertNotNull($svc->redeem($ticket));
        $this->assertNull($svc->redeem($ticket)); // second redeem fails
    }

    public function test_unknown_ticket_returns_null(): void
    {
        $this->assertNull($this->service()->redeem('not-a-real-ticket'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WebViewTicketServiceTest`
Expected: FAIL — `Class "App\Services\WebView\WebViewTicketService" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

namespace App\Services\WebView;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues and redeems opaque, single-use, short-lived tickets that hand a
 * mobile (Sanctum-authenticated) user off into a web session inside a WebView.
 *
 * The ticket string is a cryptographically-random opaque secret. Its SHA-256
 * is the cache key; the cache value carries the binding payload. Redemption is
 * atomic (Cache::pull = get+forget) so a ticket can be used at most once.
 */
class WebViewTicketService
{
    private const TTL_SECONDS = 60;

    private const PREFIX = 'webview_ticket:';

    public function issue(User $user, string $deviceId, string $targetPath): string
    {
        $ticket = Str::random(64);

        Cache::put(self::key($ticket), [
            'user_id' => $user->id,
            'token_id' => optional($user->currentAccessToken())->id,
            'device_id' => $deviceId,
            'target_path' => $targetPath,
        ], self::TTL_SECONDS);

        return $ticket;
    }

    /** @return array{user_id:int,token_id:int|null,device_id:string,target_path:string}|null */
    public function redeem(string $ticket): ?array
    {
        if ($ticket === '') {
            return null;
        }

        return Cache::pull(self::key($ticket));
    }

    private static function key(string $ticket): string
    {
        return self::PREFIX.hash('sha256', $ticket);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=WebViewTicketServiceTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/WebView/WebViewTicketService.php tests/Unit/WebView/WebViewTicketServiceTest.php
git commit -m "feat(webview): single-use cache-backed handoff ticket service"
```

---

## Task 2: webview-ticket API endpoint

**Files:**
- Create: `app/Http/Controllers/Api/Auth/WebViewAuthController.php`
- Modify: `routes/api/auth.php`
- Test: `tests/Feature/WebView/WebViewHandoffTest.php` (created here, extended in Task 3)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebViewHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/auth/webview-ticket', ['target_path' => '/dashboard'])
            ->assertUnauthorized();
    }

    public function test_ticket_endpoint_returns_enter_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', [
            'target_path' => '/dashboard',
            'device_id' => 'device-1',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['url']);
    }

    public function test_ticket_endpoint_rejects_external_path(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', ['target_path' => 'https://evil.example/phish'])
            ->assertStatus(422);

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '//evil.example'])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WebViewHandoffTest`
Expected: FAIL — 404 (route not defined) on the authenticated cases.

- [ ] **Step 3: Write minimal implementation**

Create `app/Http/Controllers/Api/Auth/WebViewAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\WebView\WebViewTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * @authenticated
 *
 * POST /api/auth/webview-ticket
 *
 * Issues a single-use handoff URL the mobile WebView opens to land in an
 * authenticated web session at the requested internal path.
 */
class WebViewAuthController extends Controller
{
    public function __construct(private readonly WebViewTicketService $tickets) {}

    public function ticket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_path' => ['required', 'string', 'max:2000'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $this->sanitizeInternalPath($data['target_path']);
        $ticket = $this->tickets->issue($request->user(), $data['device_id'] ?? 'unknown', $path);

        return response()->json([
            'ok' => true,
            'url' => url('/m/enter').'?ticket='.$ticket,
        ]);
    }

    /**
     * Reject anything that is not a same-origin absolute path. Prevents the
     * handoff from being abused as an open redirect.
     */
    private function sanitizeInternalPath(string $path): string
    {
        if (
            ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '://')
            || str_contains($path, "\n")
            || str_contains($path, "\r")
        ) {
            throw ValidationException::withMessages([
                'target_path' => 'target_path must be an internal absolute path.',
            ]);
        }

        return $path;
    }
}
```

Modify `routes/api/auth.php` — inside the existing `Route::middleware('auth:sanctum')->group(...)` block, add:

```php
    Route::post('/auth/webview-ticket', [\App\Http\Controllers\Api\Auth\WebViewAuthController::class, 'ticket'])
        ->name('api.auth.webview-ticket');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=WebViewHandoffTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/Auth/WebViewAuthController.php routes/api/auth.php tests/Feature/WebView/WebViewHandoffTest.php
git commit -m "feat(webview): POST /api/auth/webview-ticket handoff endpoint"
```

---

## Task 3: /m/enter web handoff + single-use enforcement

**Files:**
- Create: `app/Http/Controllers/WebViewEntryController.php`
- Create: `resources/views/webview/session-expired.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/WebView/WebViewHandoffTest.php` (extend)

- [ ] **Step 1: Write the failing test (append these methods to `WebViewHandoffTest`)**

```php
    public function test_enter_redeems_ticket_logs_in_and_redirects_to_embed(): void
    {
        $user = User::factory()->create();
        $ticket = app(\App\Services\WebView\WebViewTicketService::class)->issue($user, 'device-1', '/dashboard');

        $this->get('/m/enter?ticket='.$ticket)
            ->assertRedirect('/dashboard?embed=1');

        $this->assertAuthenticatedAs($user);
    }

    public function test_enter_appends_embed_param_when_path_already_has_query(): void
    {
        $user = User::factory()->create();
        $ticket = app(\App\Services\WebView\WebViewTicketService::class)->issue($user, 'd', '/orders?page=2');

        $this->get('/m/enter?ticket='.$ticket)
            ->assertRedirect('/orders?page=2&embed=1');
    }

    public function test_enter_with_used_ticket_returns_419(): void
    {
        $user = User::factory()->create();
        $ticket = app(\App\Services\WebView\WebViewTicketService::class)->issue($user, 'd', '/dashboard');

        $this->get('/m/enter?ticket='.$ticket)->assertRedirect();
        $this->get('/m/enter?ticket='.$ticket)->assertStatus(419); // single-use
    }

    public function test_enter_with_unknown_ticket_returns_419(): void
    {
        $this->get('/m/enter?ticket=garbage')->assertStatus(419);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WebViewHandoffTest`
Expected: FAIL — 404 on `/m/enter`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Http/Controllers/WebViewEntryController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Services\WebView\WebViewTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * GET /m/enter?ticket=…
 *
 * Web (session) endpoint. Redeems a single-use ticket issued to a
 * Sanctum-authenticated mobile user, establishes a web session, and redirects
 * to the requested internal path in embed mode. On failure it returns a tiny
 * page (HTTP 419) that tells the WebView bridge the session expired.
 */
class WebViewEntryController extends Controller
{
    public function __construct(private readonly WebViewTicketService $tickets) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $payload = $this->tickets->redeem((string) $request->query('ticket', ''));

        if ($payload === null) {
            return response()->view('webview.session-expired', [], 419);
        }

        Auth::loginUsingId($payload['user_id']);
        $request->session()->regenerate();

        $target = $payload['target_path'];
        $separator = str_contains($target, '?') ? '&' : '?';

        return redirect()->to($target.$separator.'embed=1');
    }
}
```

Create `resources/views/webview/session-expired.blade.php`:

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session</title>
</head>
<body>
    <script>
        if (window.ReactNativeWebView) {
            window.ReactNativeWebView.postMessage(JSON.stringify({ type: 'sessionExpired' }));
        }
    </script>
    <p style="font-family: sans-serif; padding: 24px;">Session expirée. Reconnexion…</p>
</body>
</html>
```

Modify `routes/web.php` — add (top-level, so it runs in the `web` middleware group):

```php
Route::get('/m/enter', \App\Http\Controllers\WebViewEntryController::class)->name('webview.enter');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=WebViewHandoffTest`
Expected: PASS (7 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/WebViewEntryController.php resources/views/webview/session-expired.blade.php routes/web.php tests/Feature/WebView/WebViewHandoffTest.php
git commit -m "feat(webview): /m/enter session handoff with single-use enforcement"
```

---

## Task 4: Embed mode (chrome-less rendering)

**Files:**
- Create: `app/Http/Middleware/EmbedMode.php`
- Modify: `app/Http/Kernel.php:74` (add to `web` group)
- Modify: `resources/views/layouts/app.blade.php`
- Test: `tests/Feature/WebView/EmbedModeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

class EmbedModeTest extends TestCase
{
    use RefreshDatabase;

    protected function defineWebRoutes($router): void
    {
        // A throwaway route that renders the real app layout with a known slot,
        // so we can assert the chrome guard without depending on a feature page.
        Route::middleware('web')->get('/__embed_probe', function () {
            return view('layouts.app', [
                'slot' => new HtmlString('<div data-probe="content">PROBE_OK</div>'),
            ]);
        });
    }

    public function test_embed_param_hides_primary_nav_chrome(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/__embed_probe?embed=1')
            ->assertOk()
            ->assertSee('PROBE_OK', false)
            ->assertDontSee('data-chrome="primary-nav"', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=EmbedModeTest`
Expected: FAIL — the chrome marker is present (no guard yet) OR `$embedded` undefined.

- [ ] **Step 3: Write minimal implementation**

Create `app/Http/Middleware/EmbedMode.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a request as "embedded" (rendered inside a mobile WebView) when
 * ?embed=1 or the X-Embedded: 1 header is present. Views read the shared
 * `$embedded` flag to drop navigation chrome.
 */
class EmbedMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $request->boolean('embed') || $request->header('X-Embedded') === '1';
        View::share('embedded', $embedded);

        return $next($request);
    }
}
```

Modify `app/Http/Kernel.php` — inside `$middlewareGroups['web']` array (starts line 75), append:

```php
            \App\Http\Middleware\EmbedMode::class,
```

Modify `resources/views/layouts/app.blade.php`:

Replace the navigation include (line 58) with a guarded, marked block:

```blade
        @unless($embedded ?? false)
        <div data-chrome="primary-nav">
            @livewire('navigation-menu')
        </div>
        @endunless
```

Wrap the bottom-nav / install-prompt / cookie / chatbot chrome (lines 86–122) so embedded view stays clean. Replace that trailing block with:

```blade
    @unless($embedded ?? false)
        @auth
        @if(auth()->user()->isClient())
        <x-ui.mobile-bottom-nav role="client" />
        @elseif(auth()->user()->isEmploye() && request()->routeIs('employe.*'))
        <x-ui.mobile-bottom-nav role="employe" />
        @endif
        @endauth
    @endunless

    @stack('modals')
    @livewireScripts

    @auth
    @if(auth()->user()->isEmploye() && request()->routeIs('employe.*'))
    <script src="{{ asset('js/offline-mission.js') }}"></script>
    <script>
        if (window.OfflineMission) {
            setInterval(() => { window.OfflineMission.sync(); }, 10000);
            window.addEventListener('online', () => { window.OfflineMission.sync(); });
        }
    </script>
    @endif
    @endauth

    @stack('scripts')

    @unless($embedded ?? false)
    <x-mobile-bottom-nav />
    <x-pwa-install-prompt />
    <x-cookie-banner />
    @auth
        @livewire('chatbot.assistant-widget')
    @endauth
    @endunless
```

Also drop the bottom padding reserved for the mobile nav when embedded — change line 57:

```blade
    <div class="min-h-screen {{ ($embedded ?? false) ? '' : 'pb-20 sm:pb-0' }}">
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=EmbedModeTest`
Expected: PASS.

Then confirm nothing else regressed in the layout:
Run: `php artisan test --filter=WebViewHandoffTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Middleware/EmbedMode.php app/Http/Kernel.php resources/views/layouts/app.blade.php tests/Feature/WebView/EmbedModeTest.php
git commit -m "feat(webview): embed mode hides navigation chrome for WebView rendering"
```

---

## Task 5: Parity registry + /api/parity-map

**Files:**
- Create: `config/parity.php`
- Create: `app/Http/Controllers/Api/ParityMapController.php`
- Modify: `routes/api/client.php`
- Test: `tests/Feature/WebView/ParityMapTest.php`

Note on registry population: this task seeds a **representative** set covering each delivery mode and role. Filling out all 50 modules is mechanical data entry done as modules are exposed (tracked by the `responsive_verified` flag below) — not code, so it is not part of this plan's task graph.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParityMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('parity.modules', [
            ['key' => 'booking', 'title' => 'Réserver', 'icon' => 'calendar-outline', 'path' => '/client/bookings/new', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
            ['key' => 'accounting', 'title' => 'Comptabilité', 'icon' => 'document-text-outline', 'path' => '/admin/accounting', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
            ['key' => 'help', 'title' => 'Aide', 'icon' => 'help-circle-outline', 'path' => '/help', 'web' => 'native', 'mobile' => 'webview', 'roles' => [], 'responsive_verified' => true],
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/parity-map')->assertUnauthorized();
    }

    public function test_client_sees_client_and_public_modules_but_not_admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $response = $this->getJson('/api/parity-map')->assertOk();

        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('booking'));
        $this->assertTrue($keys->contains('help'));
        $this->assertFalse($keys->contains('accounting'));
    }

    public function test_each_module_exposes_mobile_delivery_mode(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']));

        $booking = collect($this->getJson('/api/parity-map')->json('data'))
            ->firstWhere('key', 'booking');

        $this->assertSame('native', $booking['mobile']);
        $this->assertSame('/client/bookings/new', $booking['path']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ParityMapTest`
Expected: FAIL — 404 (route not defined).

- [ ] **Step 3: Write minimal implementation**

Create `config/parity.php`:

```php
<?php

/**
 * Channel parity registry — the single source of truth for which surface
 * delivers each module, and (on mobile) whether it is a native screen or an
 * embedded web view.
 *
 * Per-module shape:
 *   key                 string  stable identifier
 *   title               string  display label
 *   icon                string  ionicons name used by the mobile nav
 *   path                string  internal web path (used for webview modules)
 *   web                 string  'native' (always, for now)
 *   mobile              string  'native' | 'webview'
 *   roles               array   roles that may see it ([] = everyone authenticated)
 *   responsive_verified bool    embed view checked on a narrow viewport
 *
 * Progressive native migration (sub-project 3) = flip a module's `mobile`
 * from 'webview' to 'native' here. No other code changes.
 */
return [
    'modules' => [
        // ── Native today (hot operational paths already built in Expo) ──
        ['key' => 'booking', 'title' => 'Réserver', 'icon' => 'calendar-outline', 'path' => '/client/bookings/new', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'tracking', 'title' => 'Suivi', 'icon' => 'navigate-outline', 'path' => '/client/tracking', 'web' => 'native', 'mobile' => 'native', 'roles' => ['client'], 'responsive_verified' => true],
        ['key' => 'chat', 'title' => 'Messages', 'icon' => 'chatbubble-outline', 'path' => '/chat', 'web' => 'native', 'mobile' => 'native', 'roles' => [], 'responsive_verified' => true],
        ['key' => 'missions', 'title' => 'Missions', 'icon' => 'briefcase-outline', 'path' => '/employe/missions', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],
        ['key' => 'earnings', 'title' => 'Revenus', 'icon' => 'cash-outline', 'path' => '/employe/earnings', 'web' => 'native', 'mobile' => 'native', 'roles' => ['provider'], 'responsive_verified' => true],

        // ── Long-tail served via embedded web (migrate to native later) ──
        ['key' => 'accounting', 'title' => 'Comptabilité', 'icon' => 'document-text-outline', 'path' => '/admin/accounting', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'audit', 'title' => 'Audit', 'icon' => 'shield-checkmark-outline', 'path' => '/admin/audit', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'kyb', 'title' => 'KYB', 'icon' => 'business-outline', 'path' => '/admin/kyb', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['admin'], 'responsive_verified' => false],
        ['key' => 'invoices', 'title' => 'Factures', 'icon' => 'receipt-outline', 'path' => '/client/invoices', 'web' => 'native', 'mobile' => 'webview', 'roles' => ['client'], 'responsive_verified' => false],
        ['key' => 'help', 'title' => 'Aide', 'icon' => 'help-circle-outline', 'path' => '/help', 'web' => 'native', 'mobile' => 'webview', 'roles' => [], 'responsive_verified' => true],
    ],
];
```

Create `app/Http/Controllers/Api/ParityMapController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Parity
 *
 * @authenticated
 *
 * GET /api/parity-map
 *
 * Returns the modules visible to the authenticated user, each tagged with its
 * mobile delivery mode. The mobile app builds its navigation from this list:
 * `native` → an in-app screen, `webview` → the EmbeddedModuleScreen at `path`.
 */
class ParityMapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $modules = collect(config('parity.modules', []))
            ->filter(fn (array $m) => $this->visibleTo($user, $m['roles'] ?? []))
            ->map(fn (array $m) => [
                'key' => $m['key'],
                'title' => $m['title'],
                'icon' => $m['icon'],
                'path' => $m['path'],
                'mobile' => $m['mobile'],
            ])
            ->values();

        return response()->json(['data' => $modules]);
    }

    private function visibleTo(User $user, array $roles): bool
    {
        if ($roles === []) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->hasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    private function hasRole(User $user, string $role): bool
    {
        return match ($role) {
            'client' => method_exists($user, 'isClient') && $user->isClient(),
            'provider' => method_exists($user, 'isProvider') && $user->isProvider(),
            'admin' => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            default => false,
        };
    }
}
```

Modify `routes/api/client.php` — add inside the `auth:sanctum` group (if the file has no such group, add the route under `Route::middleware('auth:sanctum')->group(...)`):

```php
Route::middleware('auth:sanctum')->get('/parity-map', \App\Http\Controllers\Api\ParityMapController::class)
    ->name('api.parity-map');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=ParityMapTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add config/parity.php app/Http/Controllers/Api/ParityMapController.php routes/api/client.php tests/Feature/WebView/ParityMapTest.php
git commit -m "feat(parity): config-driven parity registry + GET /api/parity-map"
```

---

## Task 6: PWA installability verification

**Files:**
- Test: `tests/Feature/WebView/PwaManifestTest.php`
- Modify (only if test fails): `public/manifest.webmanifest`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\WebView;

use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    public function test_manifest_is_served_and_installable(): void
    {
        $path = public_path('manifest.webmanifest');
        $this->assertFileExists($path);

        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($manifest);

        foreach (['name', 'short_name', 'start_url', 'display', 'icons'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest missing '{$key}'");
        }

        $this->assertContains($manifest['display'], ['standalone', 'fullscreen', 'minimal-ui']);
        $this->assertNotEmpty($manifest['icons']);
    }

    public function test_service_worker_is_present(): void
    {
        $this->assertFileExists(public_path('sw.js'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails or passes**

Run: `php artisan test --filter=PwaManifestTest`
Expected: PASS if the existing manifest already has the required keys. If it FAILS, proceed to Step 3.

- [ ] **Step 3: Fix the manifest (only if Step 2 failed)**

Read the current `public/manifest.webmanifest`, then ensure it contains at minimum (merge, do not blow away existing icons):

```json
{
  "name": "Brio",
  "short_name": "Brio",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#ffffff",
  "theme_color": "#2563eb",
  "icons": [
    { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=PwaManifestTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/WebView/PwaManifestTest.php public/manifest.webmanifest
git commit -m "test(pwa): assert installable manifest + service worker present"
```

---

## Task 7: Mobile deps — webUrl env, react-native-webview, jest wiring

**Files:**
- Modify: `mobile/shared/src/config/env.ts`
- Create: `mobile/client/__mocks__/react-native-webview.tsx`
- Modify: `mobile/client/jest.config.ts`
- Modify: `mobile/client/tsconfig.json`
- Install: `react-native-webview` in `mobile/client`

- [ ] **Step 1: Add `webUrl` to env**

Modify `mobile/shared/src/config/env.ts` — add a `webUrl` line to the `env` object:

```ts
export const env = {
  apiUrl: optionalEnv('EXPO_PUBLIC_API_URL', 'http://localhost:8000/api'),
  webUrl: optionalEnv('EXPO_PUBLIC_WEB_URL', 'http://localhost:8000'),
  stripePublishableKey: optionalEnv('EXPO_PUBLIC_STRIPE_PUBLISHABLE_KEY'),
  sentryDsn: optionalEnv('EXPO_PUBLIC_SENTRY_DSN'),
} as const;
```

- [ ] **Step 2: Install react-native-webview**

Run (PowerShell, from repo root):
```powershell
cd mobile/client; npx expo install react-native-webview
```
Expected: `react-native-webview` added to `mobile/client/package.json` dependencies.

- [ ] **Step 3: Create the Jest mock**

Create `mobile/client/__mocks__/react-native-webview.tsx`:

```tsx
import React from 'react';
import { View } from 'react-native';

// Minimal WebView stub: renders nothing native but exposes its props via
// testID + accessibilityState so tests can assert source.uri and trigger
// onMessage / onError through the ref-less prop callbacks.
export const WebView = React.forwardRef(function WebView(props: any, _ref) {
  return (
    <View
      testID="mock-webview"
      // expose the loaded uri for assertions
      accessibilityLabel={props?.source?.uri ?? ''}
      onLayout={() => props?.onLoadEnd?.()}
    >
      {props.children}
    </View>
  );
});

export default WebView;
```

- [ ] **Step 4: Wire jest moduleNameMapper**

Modify `mobile/client/jest.config.ts` — in `moduleNameMapper`, add the two shared-path aliases **above** the `'^@/(.*)$'` catch-all line, and the webview mock:

```ts
    '^@/webview(.*)$': '<rootDir>/../shared/src/webview$1',
    '^@/parity(.*)$': '<rootDir>/../shared/src/parity$1',
    '^react-native-webview$': '<rootDir>/__mocks__/react-native-webview.tsx',
```

- [ ] **Step 5: Wire tsconfig paths**

Modify `mobile/client/tsconfig.json` — under `compilerOptions.paths`, add (mirroring the existing shared aliases like `@/auth`):

```json
      "@/webview": ["../shared/src/webview"],
      "@/webview/*": ["../shared/src/webview/*"],
      "@/parity": ["../shared/src/parity"],
      "@/parity/*": ["../shared/src/parity/*"],
```

- [ ] **Step 6: Verify typecheck still passes**

Run (PowerShell, from `mobile/client`):
```powershell
npm.cmd run typecheck
```
Expected: exit 0 (no type errors introduced).

- [ ] **Step 7: Commit**

```bash
git add mobile/shared/src/config/env.ts mobile/client/__mocks__/react-native-webview.tsx mobile/client/jest.config.ts mobile/client/tsconfig.json mobile/client/package.json mobile/client/package-lock.json
git commit -m "chore(mobile): add webUrl env + react-native-webview dep + jest/ts wiring"
```

---

## Task 8: webBridge protocol

**Files:**
- Create: `mobile/shared/src/webview/bridge.ts`
- Test: `mobile/shared/src/webview/__tests__/bridge.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
import { parseBridgeMessage, INJECTED_BRIDGE_JS } from '../bridge';

describe('parseBridgeMessage', () => {
  it('parses each known message type', () => {
    expect(parseBridgeMessage(JSON.stringify({ type: 'ready' }))).toEqual({ type: 'ready' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'requestBack' }))).toEqual({ type: 'requestBack' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'openNative', route: '/booking/new' })))
      .toEqual({ type: 'openNative', route: '/booking/new' });
    expect(parseBridgeMessage(JSON.stringify({ type: 'sessionExpired' }))).toEqual({ type: 'sessionExpired' });
  });

  it('rejects unknown types and malformed json', () => {
    expect(parseBridgeMessage(JSON.stringify({ type: 'evil' }))).toBeNull();
    expect(parseBridgeMessage('not json')).toBeNull();
    expect(parseBridgeMessage(JSON.stringify({ noType: true }))).toBeNull();
  });

  it('exposes injected JS that posts a ready event', () => {
    expect(INJECTED_BRIDGE_JS).toContain('ReactNativeWebView');
    expect(INJECTED_BRIDGE_JS).toContain("type:'ready'");
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (PowerShell, from `mobile/client`):
```powershell
npx jest bridge.test
```
Expected: FAIL — cannot find module `../bridge`.

- [ ] **Step 3: Write minimal implementation**

Create `mobile/shared/src/webview/bridge.ts`:

```ts
/**
 * Fixed, enumerated message protocol between an embedded web page and the
 * native WebView host. Not arbitrary RPC — only these shapes are accepted.
 */
export type BridgeMessage =
  | { type: 'ready' }
  | { type: 'requestBack' }
  | { type: 'openNative'; route: string }
  | { type: 'sessionExpired' }
  | { type: 'error'; message?: string };

const KNOWN_TYPES = ['ready', 'requestBack', 'openNative', 'sessionExpired', 'error'];

export function parseBridgeMessage(raw: string): BridgeMessage | null {
  try {
    const msg = JSON.parse(raw);
    if (!msg || typeof msg.type !== 'string' || !KNOWN_TYPES.includes(msg.type)) {
      return null;
    }
    if (msg.type === 'openNative' && typeof msg.route !== 'string') {
      return null;
    }
    return msg as BridgeMessage;
  } catch {
    return null;
  }
}

/**
 * Injected into every embedded page. Exposes window.BrioBridge for pages
 * that want to hand off to native, and announces readiness. Trailing `true;`
 * is required by react-native-webview's injectedJavaScript contract.
 */
export const INJECTED_BRIDGE_JS = `
(function(){
  if (window.BrioBridge) { return; }
  var post = function(msg){ if(window.ReactNativeWebView){ window.ReactNativeWebView.postMessage(JSON.stringify(msg)); } };
  window.BrioBridge = {
    post: post,
    back: function(){ post({type:'requestBack'}); },
    openNative: function(route){ post({type:'openNative', route: route}); }
  };
  post({type:'ready'});
})();
true;
`;
```

- [ ] **Step 4: Run test to verify it passes**

Run (from `mobile/client`): `npx jest bridge.test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mobile/shared/src/webview/bridge.ts mobile/shared/src/webview/__tests__/bridge.test.ts
git commit -m "feat(mobile): webview native<->web bridge message protocol"
```

---

## Task 9: fetchWebViewUrl

**Files:**
- Create: `mobile/shared/src/webview/useWebViewTicket.ts`
- Test: `mobile/shared/src/webview/__tests__/useWebViewTicket.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
import { fetchWebViewUrl } from '../useWebViewTicket';
import { apiClient } from '@/api';

jest.mock('@/api', () => ({
  apiClient: { post: jest.fn() },
  ApiError: class ApiError extends Error {},
}));

describe('fetchWebViewUrl', () => {
  it('posts target_path + device_id and returns the handoff url', async () => {
    (apiClient.post as jest.Mock).mockResolvedValue({ data: { ok: true, url: 'https://app/m/enter?ticket=abc' } });

    const url = await fetchWebViewUrl('/admin/audit', 'device-9');

    expect(apiClient.post).toHaveBeenCalledWith('/auth/webview-ticket', {
      target_path: '/admin/audit',
      device_id: 'device-9',
    });
    expect(url).toBe('https://app/m/enter?ticket=abc');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `mobile/client`): `npx jest useWebViewTicket.test`
Expected: FAIL — cannot find module `../useWebViewTicket`.

- [ ] **Step 3: Write minimal implementation**

Create `mobile/shared/src/webview/useWebViewTicket.ts`:

```ts
import { apiClient } from '@/api';

/**
 * Requests a single-use handoff URL for an authenticated WebView session at
 * the given internal web path. The returned URL is loaded directly by the
 * WebView; it logs the user into a web session and redirects to <path>?embed=1.
 */
export async function fetchWebViewUrl(targetPath: string, deviceId: string): Promise<string> {
  const res = await apiClient.post('/auth/webview-ticket', {
    target_path: targetPath,
    device_id: deviceId,
  });
  return res.data.url as string;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run (from `mobile/client`): `npx jest useWebViewTicket.test`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add mobile/shared/src/webview/useWebViewTicket.ts mobile/shared/src/webview/__tests__/useWebViewTicket.test.ts
git commit -m "feat(mobile): fetchWebViewUrl handoff client"
```

---

## Task 10: EmbeddedModuleScreen (WebView host)

**Files:**
- Create: `mobile/shared/src/webview/EmbeddedModuleScreen.tsx`
- Test: `mobile/shared/src/webview/__tests__/EmbeddedModuleScreen.test.tsx`

- [ ] **Step 1: Write the failing test**

```tsx
import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import { EmbeddedModuleScreen } from '../EmbeddedModuleScreen';
import * as ticket from '../useWebViewTicket';

jest.mock('../useWebViewTicket');

describe('EmbeddedModuleScreen', () => {
  beforeEach(() => jest.clearAllMocks());

  it('fetches a handoff url for the given path and loads it in the WebView', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/admin/audit" title="Audit" deviceId="dev-1" />,
    );

    await waitFor(() => {
      expect(ticket.fetchWebViewUrl).toHaveBeenCalledWith('/admin/audit', 'dev-1');
      expect(getByTestId('mock-webview').props.accessibilityLabel).toBe('https://app/m/enter?ticket=t');
    });
  });

  it('shows an error state when the handoff fails', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockRejectedValue(new Error('offline'));

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/admin/audit" title="Audit" deviceId="dev-1" />,
    );

    await waitFor(() => expect(getByTestId('embedded-error')).toBeTruthy());
  });

  it('calls onOpenNative when the page posts an openNative bridge message', async () => {
    (ticket.fetchWebViewUrl as jest.Mock).mockResolvedValue('https://app/m/enter?ticket=t');
    const onOpenNative = jest.fn();

    const { getByTestId } = render(
      <EmbeddedModuleScreen path="/x" title="X" deviceId="d" onOpenNative={onOpenNative} />,
    );

    await waitFor(() => getByTestId('mock-webview'));
    const webview = getByTestId('mock-webview');
    webview.props.onMessage({ nativeEvent: { data: JSON.stringify({ type: 'openNative', route: '/booking/new' }) } });

    expect(onOpenNative).toHaveBeenCalledWith('/booking/new');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `mobile/client`): `npx jest EmbeddedModuleScreen.test`
Expected: FAIL — cannot find module `../EmbeddedModuleScreen`.

- [ ] **Step 3: Write minimal implementation**

Create `mobile/shared/src/webview/EmbeddedModuleScreen.tsx`:

```tsx
import React, { useCallback, useEffect, useState } from 'react';
import { View, ActivityIndicator } from 'react-native';
import { WebView } from 'react-native-webview';
import { fetchWebViewUrl } from './useWebViewTicket';
import { parseBridgeMessage, INJECTED_BRIDGE_JS } from './bridge';
import { ErrorState } from '@/ui';
import { colors } from '@/theme';

export interface EmbeddedModuleScreenProps {
  /** Internal web path to render (e.g. '/admin/audit'). */
  path: string;
  /** Display title (drawn by the native header in the route wrapper). */
  title: string;
  /** Stable device identifier for ticket binding. */
  deviceId: string;
  /** Called when an embedded page hands off to a native route. */
  onOpenNative?: (route: string) => void;
  /** Called when the bridge requests closing the embedded view. */
  onRequestBack?: () => void;
}

type Status = 'loading' | 'ready' | 'error';

/**
 * Renders any existing web module inside an authenticated WebView. The user is
 * already logged in (Sanctum); a single-use ticket establishes a disposable
 * web session, so embedded pages load without a second login.
 */
export function EmbeddedModuleScreen({
  path,
  deviceId,
  onOpenNative,
  onRequestBack,
}: EmbeddedModuleScreenProps) {
  const [url, setUrl] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>('loading');

  const load = useCallback(async () => {
    setStatus('loading');
    try {
      setUrl(await fetchWebViewUrl(path, deviceId));
      setStatus('ready');
    } catch {
      setStatus('error');
    }
  }, [path, deviceId]);

  useEffect(() => {
    load();
  }, [load]);

  const onMessage = useCallback(
    (event: { nativeEvent: { data: string } }) => {
      const msg = parseBridgeMessage(event.nativeEvent.data);
      if (!msg) return;
      switch (msg.type) {
        case 'openNative':
          onOpenNative?.(msg.route);
          break;
        case 'requestBack':
          onRequestBack?.();
          break;
        case 'sessionExpired':
          load(); // silent re-handoff using the still-valid Sanctum token
          break;
        case 'error':
          setStatus('error');
          break;
      }
    },
    [onOpenNative, onRequestBack, load],
  );

  if (status === 'error') {
    return (
      <View testID="embedded-error" style={{ flex: 1 }}>
        <ErrorState
          title="Indisponible hors-ligne"
          message="Cette section nécessite une connexion. Réessayez."
          onRetry={load}
        />
      </View>
    );
  }

  if (status === 'loading' || !url) {
    return (
      <View testID="embedded-loading" style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: colors.surface[50] }}>
        <ActivityIndicator size="large" color={colors.brand[500]} />
      </View>
    );
  }

  return (
    <WebView
      source={{ uri: url }}
      injectedJavaScript={INJECTED_BRIDGE_JS}
      onMessage={onMessage}
      onError={() => setStatus('error')}
      onHttpError={(e: any) => {
        if ((e?.nativeEvent?.statusCode ?? 0) >= 500) setStatus('error');
      }}
      startInLoadingState
    />
  );
}
```

Note: `ErrorState` and `colors` are already exported from `@brio/shared` (`src/index.ts`) and importable via `@/ui` / `@/theme` (confirmed in the shared index). `ErrorState` accepts `title`, `message`, `onRetry` — verify the prop names against `mobile/shared/src/ui/ErrorState` and adjust if different.

- [ ] **Step 4: Run test to verify it passes**

Run (from `mobile/client`): `npx jest EmbeddedModuleScreen.test`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add mobile/shared/src/webview/EmbeddedModuleScreen.tsx mobile/shared/src/webview/__tests__/EmbeddedModuleScreen.test.tsx
git commit -m "feat(mobile): EmbeddedModuleScreen WebView host with bridge + error states"
```

---

## Task 11: fetchParityMap (with offline cache)

**Files:**
- Create: `mobile/shared/src/parity/useParityMap.ts`
- Test: `mobile/shared/src/parity/__tests__/useParityMap.test.ts`

- [ ] **Step 1: Write the failing test**

```ts
import { fetchParityMap } from '../useParityMap';
import { apiClient } from '@/api';
import AsyncStorage from '@react-native-async-storage/async-storage';

jest.mock('@/api', () => ({ apiClient: { get: jest.fn() }, ApiError: class extends Error {} }));
jest.mock('@react-native-async-storage/async-storage', () => ({
  setItem: jest.fn(), getItem: jest.fn(),
}));

const MODULES = [{ key: 'help', title: 'Aide', icon: 'help-circle-outline', path: '/help', mobile: 'webview' }];

describe('fetchParityMap', () => {
  beforeEach(() => jest.clearAllMocks());

  it('returns network data and caches it', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: MODULES } });

    const result = await fetchParityMap();

    expect(apiClient.get).toHaveBeenCalledWith('/parity-map');
    expect(result).toEqual(MODULES);
    expect(AsyncStorage.setItem).toHaveBeenCalledWith('brio_parity_map', JSON.stringify(MODULES));
  });

  it('falls back to cache when the network fails', async () => {
    (apiClient.get as jest.Mock).mockRejectedValue(new Error('offline'));
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(JSON.stringify(MODULES));

    const result = await fetchParityMap();
    expect(result).toEqual(MODULES);
  });

  it('rethrows when network fails and no cache exists', async () => {
    (apiClient.get as jest.Mock).mockRejectedValue(new Error('offline'));
    (AsyncStorage.getItem as jest.Mock).mockResolvedValue(null);

    await expect(fetchParityMap()).rejects.toThrow('offline');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `mobile/client`): `npx jest useParityMap.test`
Expected: FAIL — cannot find module `../useParityMap`.

- [ ] **Step 3: Write minimal implementation**

Create `mobile/shared/src/parity/useParityMap.ts`:

```ts
import AsyncStorage from '@react-native-async-storage/async-storage';
import { apiClient } from '@/api';

export interface ParityModule {
  key: string;
  title: string;
  icon: string;
  path: string;
  mobile: 'native' | 'webview';
}

const CACHE_KEY = 'brio_parity_map';

/**
 * Fetches the per-user parity map (which modules exist and how each is
 * delivered on mobile). Caches the last successful response so the navigation
 * survives a cold offline launch; on network failure it serves the cache.
 */
export async function fetchParityMap(): Promise<ParityModule[]> {
  try {
    const res = await apiClient.get('/parity-map');
    const data = res.data.data as ParityModule[];
    await AsyncStorage.setItem(CACHE_KEY, JSON.stringify(data));
    return data;
  } catch (err) {
    const cached = await AsyncStorage.getItem(CACHE_KEY);
    if (cached) {
      return JSON.parse(cached) as ParityModule[];
    }
    throw err;
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run (from `mobile/client`): `npx jest useParityMap.test`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add mobile/shared/src/parity/useParityMap.ts mobile/shared/src/parity/__tests__/useParityMap.test.ts
git commit -m "feat(mobile): fetchParityMap with offline cache fallback"
```

---

## Task 12: Export new shared modules

**Files:**
- Modify: `mobile/shared/src/index.ts`

- [ ] **Step 1: Add exports**

Append to `mobile/shared/src/index.ts`:

```ts
// WebView (parity foundation)
export { EmbeddedModuleScreen } from './webview/EmbeddedModuleScreen';
export type { EmbeddedModuleScreenProps } from './webview/EmbeddedModuleScreen';
export { parseBridgeMessage, INJECTED_BRIDGE_JS } from './webview/bridge';
export type { BridgeMessage } from './webview/bridge';
export { fetchWebViewUrl } from './webview/useWebViewTicket';

// Parity
export { fetchParityMap } from './parity/useParityMap';
export type { ParityModule } from './parity/useParityMap';
```

- [ ] **Step 2: Verify typecheck**

Run (from `mobile/client`): `npm.cmd run typecheck`
Expected: exit 0.

- [ ] **Step 3: Commit**

```bash
git add mobile/shared/src/index.ts
git commit -m "feat(mobile): export webview + parity modules from shared"
```

---

## Task 13: EmbeddedModuleRoute + RootNavigator wiring

**Files:**
- Create: `mobile/client/src/screens/EmbeddedModuleRoute.tsx`
- Modify: `mobile/client/src/navigation/types.ts`
- Modify: `mobile/client/src/navigation/RootNavigator.tsx`

- [ ] **Step 1: Add the route param type**

Modify `mobile/client/src/navigation/types.ts` — add to `RootStackParamList`:

```ts
  EmbeddedModule: { path: string; title: string };
```

- [ ] **Step 2: Create the route wrapper**

Create `mobile/client/src/screens/EmbeddedModuleRoute.tsx`:

```tsx
import React from 'react';
import { EmbeddedModuleScreen } from '@/webview';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useDeviceId } from '@/hooks/useDeviceId';

type Props = NativeStackScreenProps<RootStackParamList, 'EmbeddedModule'>;

/**
 * Connects the shared WebView host to React Navigation: titles the native
 * header, routes openNative handoffs to real native screens, and maps the
 * bridge's back request to navigation.goBack().
 */
export function EmbeddedModuleRoute({ route, navigation }: Props) {
  const { path, title } = route.params;
  const deviceId = useDeviceId();

  React.useLayoutEffect(() => {
    navigation.setOptions({ title });
  }, [navigation, title]);

  return (
    <EmbeddedModuleScreen
      path={path}
      title={title}
      deviceId={deviceId}
      onRequestBack={() => navigation.goBack()}
      onOpenNative={(target) => {
        // Best-effort: an embedded page asked for a native screen. Until a
        // path→native map exists (sub-project 3), re-embed the target.
        navigation.push('EmbeddedModule', { path: target, title });
      }}
    />
  );
}
```

Note: `useDeviceId` — if a device-id hook does not already exist in `mobile/client/src/hooks`, create a tiny one:

Create `mobile/client/src/hooks/useDeviceId.ts` (only if absent):

```ts
import { useEffect, useState } from 'react';
import * as Application from 'expo-application';

/** Stable per-install identifier used to bind WebView handoff tickets. */
export function useDeviceId(): string {
  const [id, setId] = useState('unknown');
  useEffect(() => {
    const value =
      Application.getAndroidId?.() ??
      // iOS: vendor id is async; fall back to a constant install marker
      'ios-device';
    setId(typeof value === 'string' ? value : 'unknown');
  }, []);
  return id;
}
```

(If `expo-application` is not installed, return the constant `'mobile'` instead — the device id is only used for audit binding, not security enforcement.)

- [ ] **Step 3: Register the screen in RootNavigator**

Modify `mobile/client/src/navigation/RootNavigator.tsx` — add the import:

```tsx
import { EmbeddedModuleRoute } from '@/screens/EmbeddedModuleRoute';
```

and add a `<Stack.Screen>` inside the authenticated `<>...</>` block (alongside the others):

```tsx
            <Stack.Screen
              name="EmbeddedModule"
              component={EmbeddedModuleRoute}
              options={{ headerShown: true }}
            />
```

- [ ] **Step 4: Verify typecheck**

Run (from `mobile/client`): `npm.cmd run typecheck`
Expected: exit 0.

- [ ] **Step 5: Commit**

```bash
git add mobile/client/src/screens/EmbeddedModuleRoute.tsx mobile/client/src/navigation/types.ts mobile/client/src/navigation/RootNavigator.tsx mobile/client/src/hooks/useDeviceId.ts
git commit -m "feat(mobile): EmbeddedModule route wired into client navigator"
```

---

## Task 14: ModuleHubScreen (parity-driven routing)

**Files:**
- Create: `mobile/client/src/screens/ModuleHubScreen.tsx`
- Test: `mobile/client/src/screens/__tests__/ModuleHubScreen.test.tsx`

This is the screen that proves total parity: it lists every module the user can see and routes each to a native screen or the embedded WebView purely from its `mobile` flag.

- [ ] **Step 1: Write the failing test**

```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');

const navigate = jest.fn();
const navigation: any = { navigate };

const MODULES = [
  { key: 'booking', title: 'Réserver', icon: 'calendar-outline', path: '/client/bookings/new', mobile: 'native' },
  { key: 'accounting', title: 'Comptabilité', icon: 'document-text-outline', path: '/admin/accounting', mobile: 'webview' },
];

describe('ModuleHubScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(MODULES);
  });

  it('routes a webview module to the EmbeddedModule screen with its path', async () => {
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Comptabilité'));

    fireEvent.press(getByText('Comptabilité'));

    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', {
      path: '/admin/accounting',
      title: 'Comptabilité',
    });
  });

  it('routes a native module to its native screen, not the WebView', async () => {
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Réserver'));

    fireEvent.press(getByText('Réserver'));

    // native modules go to a mapped native route — never EmbeddedModule
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
    expect(navigate).toHaveBeenCalledWith('BookingWizard');
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `mobile/client`): `npx jest ModuleHubScreen.test`
Expected: FAIL — cannot find module `../ModuleHubScreen`.

- [ ] **Step 3: Write minimal implementation**

Create `mobile/client/src/screens/ModuleHubScreen.tsx`:

```tsx
import React, { useEffect, useState } from 'react';
import { ScrollView, Pressable, Text, View } from 'react-native';
import { fetchParityMap, type ParityModule } from '@/parity';
import { Icon, Screen } from '@/ui';
import { colors, spacing } from '@/theme';

/**
 * Maps a `native` parity module key to its in-app route name. As modules are
 * migrated (sub-project 3), add entries here; until then a native flag with no
 * mapping safely falls back to embedded web.
 */
const NATIVE_ROUTES: Record<string, { screen: string; params?: object }> = {
  booking: { screen: 'BookingWizard' },
  tracking: { screen: 'MissionTracking' },
  chat: { screen: 'ChatList' },
};

export function ModuleHubScreen({ navigation }: { navigation: any }) {
  const [modules, setModules] = useState<ParityModule[]>([]);

  useEffect(() => {
    fetchParityMap().then(setModules).catch(() => setModules([]));
  }, []);

  const open = (m: ParityModule) => {
    const native = m.mobile === 'native' ? NATIVE_ROUTES[m.key] : undefined;
    if (native) {
      navigation.navigate(native.screen, native.params);
      return;
    }
    navigation.navigate('EmbeddedModule', { path: m.path, title: m.title });
  };

  return (
    <Screen>
      <ScrollView contentContainerStyle={{ padding: spacing.md }}>
        {modules.map((m) => (
          <Pressable
            key={m.key}
            onPress={() => open(m)}
            style={{ flexDirection: 'row', alignItems: 'center', paddingVertical: spacing.md }}
          >
            <Icon name={m.icon as any} size={22} color={colors.brand[500]} />
            <View style={{ marginLeft: spacing.md }}>
              <Text style={{ fontSize: 16, color: colors.surface[900] }}>{m.title}</Text>
            </View>
          </Pressable>
        ))}
      </ScrollView>
    </Screen>
  );
}
```

Note: `Icon`, `Screen` are exported from `@brio/shared` (confirmed). `ChatList` is the existing chat-list route name — verify the exact name in `RootNavigator.tsx`/`types.ts` and adjust `NATIVE_ROUTES.chat` if different.

- [ ] **Step 4: Run test to verify it passes**

Run (from `mobile/client`): `npx jest ModuleHubScreen.test`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add mobile/client/src/screens/ModuleHubScreen.tsx mobile/client/src/screens/__tests__/ModuleHubScreen.test.tsx
git commit -m "feat(mobile): ModuleHub routes modules native-vs-webview from parity map"
```

---

## Task 15: Definition-of-done — flag-flip routing test

**Files:**
- Test: `mobile/client/src/screens/__tests__/ParityFlagFlip.test.tsx`

This validates DoD #6: flipping a module's `mobile` flag from `webview` → `native` re-routes its nav entry with no other code change.

- [ ] **Step 1: Write the test**

```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');

const navigate = jest.fn();
const navigation: any = { navigate };

// Same module, two delivery modes — nothing else changes.
const asWebview = [{ key: 'booking', title: 'Réserver', icon: 'calendar-outline', path: '/client/bookings/new', mobile: 'webview' }];
const asNative = [{ key: 'booking', title: 'Réserver', icon: 'calendar-outline', path: '/client/bookings/new', mobile: 'native' }];

describe('parity flag flip re-routes with no code change', () => {
  beforeEach(() => jest.clearAllMocks());

  it('webview flag → EmbeddedModule', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asWebview);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Réserver'));
    fireEvent.press(getByText('Réserver'));
    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', { path: '/client/bookings/new', title: 'Réserver' });
  });

  it('native flag → native screen', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(asNative);
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Réserver'));
    fireEvent.press(getByText('Réserver'));
    expect(navigate).toHaveBeenCalledWith('BookingWizard', undefined);
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
  });
});
```

- [ ] **Step 2: Run test to verify it passes**

Run (from `mobile/client`): `npx jest ParityFlagFlip.test`
Expected: PASS (2 tests).

- [ ] **Step 3: Commit**

```bash
git add mobile/client/src/screens/__tests__/ParityFlagFlip.test.tsx
git commit -m "test(parity): flag flip re-routes module native vs webview (DoD #6)"
```

---

## Task 16: Full-suite verification + DoD checklist

**Files:** none (verification + final commit).

- [ ] **Step 1: Backend suite green**

Run (PowerShell, repo root): `php artisan test --filter=WebView; php artisan test --filter=ParityMapTest`
Expected: all WebView + parity tests PASS.

- [ ] **Step 2: Static analysis + formatting unchanged**

Run: `vendor/bin/pint --test; if ($?) { vendor/bin/phpstan analyse --memory-limit=1G }`
Expected: Pint reports no style issues on new files; PHPStan clean (no new findings).

If Pint reports issues, run `vendor/bin/pint` and re-commit.

- [ ] **Step 3: Mobile suite green + typecheck**

Run (from `mobile/client`): `npm.cmd run typecheck; if ($?) { npx jest webview parity ModuleHub ParityFlagFlip EmbeddedModuleScreen bridge useWebViewTicket useParityMap }`
Expected: typecheck exit 0; all new Jest tests PASS.

- [ ] **Step 4: Confirm DoD against the spec**

Verify each spec DoD item has a corresponding green test or is demonstrably true:
1. Reach every module after one native login → ModuleHub + EmbeddedModule + /api/parity-map (Tasks 5, 13, 14).
2. Embedded pages chrome-less + role-correct → EmbedModeTest + ParityMapTest (Tasks 4, 5).
3. Silent session re-handoff + clean logout → `sessionExpired` path in EmbeddedModuleScreen (Task 10) + single-use enforcement (Task 3).
4. Web responsive in embed mode + installable PWA → EmbedModeTest + PwaManifestTest (Tasks 4, 6).
5. Test suites green and in always-run suites → Steps 1–3 here (all SQLite + mocked, no skips added).
6. Flag flip re-routes nav → ParityFlagFlip.test (Task 15).

- [ ] **Step 5: Final commit (only if Pint reformatted anything)**

```bash
git add -A
git commit -m "chore(parity): pint formatting on parity foundation files"
```

---

## Self-review notes (already applied)

- **Spec coverage:** all six units (ticket service, ticket endpoint, /m/enter, embed mode, parity registry+endpoint, EmbeddedModuleScreen, webBridge) + responsiveness/PWA + native-nav wiring + DoD flag-flip each map to a task. The full responsiveness sweep across 217 components is explicitly iterative (tracked via `responsive_verified` in `config/parity.php`), not a code task here — stated in Task 5.
- **Type/name consistency:** `fetchWebViewUrl`, `fetchParityMap`, `parseBridgeMessage`, `INJECTED_BRIDGE_JS`, `EmbeddedModuleScreen`, `ParityModule`, route name `EmbeddedModule`, cache key `brio_parity_map`, ticket prefix `webview_ticket:`, embed param `embed=1`, marker `data-chrome="primary-nav"` are used consistently across backend, shared, and client tasks.
- **Adapt-on-contact flags** (called out inline, not placeholders): `ErrorState` prop names, the `ChatList` native route name, and `expo-application` availability — each has a concrete fallback specified.
```
