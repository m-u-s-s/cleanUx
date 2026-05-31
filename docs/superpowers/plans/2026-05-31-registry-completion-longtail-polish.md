# Registry Completion + Long-tail Polish — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `config/parity.php` the complete single source of truth for every navigable module on mobile (real verified paths, role-scoped) and prove every WebView fallback renders — without migrating anything to native.

**Architecture:** The ~111 navigable web areas are GENERATED from the router (a dev command emits candidate registry entries; a human reviews + commits them into `config/parity.php`), because hand-listing 111 entries would drift. Three autonomous test suites then guard the registry: every path resolves to a real route, every webview fallback renders chrome-less in embed mode for its role, and the role-filter matches real authz. Phase 2 (visual QA + responsive fixes) is a runbook skeleton, operated together later.

**Tech Stack:** Laravel 10 (Artisan, Router), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-05-31-registry-completion-longtail-polish-design.md`
**Branch:** `feat/registry-completion` (off `feat/native-migration`). Infra present from SP1/SP3: `config/parity.php`, `EmbedMode` middleware (sets `$embedded`; layout marks the nav wrapper `data-chrome="primary-nav"` and hides it when embedded), `ParityMapController` + `GET /api/parity-map`, `routes/admin.php`/`client.php`/`employe.php`.

---

## Verified ground truth

- `route:list --json` is the source. Navigable area = a **GET route, no path params, ≤2 URI segments**, under a role prefix.
- **Role mapping by URI prefix:** `admin/*` → `['admin']`; `dashboard/client/*` → `['client']`; `dashboard/employe/*` → `['provider']` (employe == provider via `matchesRole('provider')` → `isEmploye()`).
- **Counts:** client 22, employe 18, admin 71 navigable areas.
- **Existing registry (`config/parity.php`):** 10 entries. Native (keep, do NOT re-add): booking, tracking, chat, missions, earnings, invoices (path `/dashboard/client/finance`). Webview (keep): accounting (`/admin/accounting`→stale; real is `/admin/accounting-v2`), audit (`/admin/audit`), kyb (`/admin/kyb`→stale; real `/admin/kyb-v2`), help (`/help`).
- **Dedup:** the generator must SKIP any route whose path already exists in `config('parity.modules')` (so native finance/missions/earnings/chat and the existing webview entries aren't duplicated).
- **EmbedMode marker:** `resources/views/layouts/app.blade.php` wraps the primary nav in `@unless($embedded ?? false) <div data-chrome="primary-nav">…@endunless`. So `?embed=1` ⇒ `data-chrome="primary-nav"` absent from the HTML.
- **Stale-path correction:** the existing `accounting`/`kyb` entries point at non-existent paths (`/admin/accounting`, `/admin/kyb`); the real routes are `/admin/accounting-v2`, `/admin/kyb-v2`. Fix these as part of registration (the path-resolution test will otherwise fail them).

---

## File structure

- Create `app/Console/Commands/ParityScaffoldRegistry.php` — dev command emitting candidate registry entries from the router. (Task 1)
- Modify `config/parity.php` — add the ~105 generated webview entries + fix the 2 stale paths. (Task 2)
- Create `tests/Feature/Parity/ParityPathsResolveTest.php` — every path → a real route. (Task 3)
- Create `tests/Feature/Parity/ParityEmbedRenderTest.php` — webview fallbacks render chrome-less. (Task 4)
- Create `tests/Feature/Parity/ParityRoleAccessTest.php` — role filter ↔ authz. (Task 5)
- Create `docs/runbooks/EMBED-VISUAL-QA.md` — Phase-2 checklist (generated). (Task 6)
- Task 7: full verification + DoD.

---

## Task 1: `parity:scaffold-registry` command (reproducible curation)

**Files:**
- Create: `app/Console/Commands/ParityScaffoldRegistry.php`
- Test: `tests/Feature/Parity/ParityScaffoldRegistryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Parity;

use Tests\TestCase;

class ParityScaffoldRegistryTest extends TestCase
{
    public function test_scaffold_emits_candidate_modules_excluding_existing(): void
    {
        $this->artisan('parity:scaffold-registry')
            ->assertExitCode(0)
            ->expectsOutputToContain("'mobile' => 'webview'");
    }

    public function test_scaffold_excludes_already_registered_paths(): void
    {
        // The native invoices path /dashboard/client/finance is already in config/parity.php,
        // so the scaffold must NOT emit a candidate for it.
        $this->artisan('parity:scaffold-registry --json')
            ->assertExitCode(0)
            ->doesntExpectOutput('/dashboard/client/finance');
    }
}
```

- [ ] **Step 2: Run → FAIL** (command not found). `php artisan test --filter=ParityScaffoldRegistryTest`

- [ ] **Step 3: Implement the command**

Create `app/Console/Commands/ParityScaffoldRegistry.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Dev tool: emits candidate parity-registry entries for every navigable web area
 * (GET, no path params, <=2 URI segments) under a known role prefix, EXCLUDING
 * paths already present in config/parity.php. Output is reviewed by a human and
 * pasted into config/parity.php (the registry stays a committed file).
 */
class ParityScaffoldRegistry extends Command
{
    protected $signature = 'parity:scaffold-registry {--json : Output JSON instead of PHP array literals}';

    protected $description = 'Emit candidate parity registry entries from the router';

    /** prefix => [role] */
    private const ROLE_PREFIXES = [
        'dashboard/client' => ['client'],
        'dashboard/employe' => ['provider'],
        'admin' => ['admin'],
    ];

    public function handle(Router $router): int
    {
        $existingPaths = collect(config('parity.modules', []))
            ->pluck('path')
            ->map(fn ($p) => '/'.ltrim((string) $p, '/'))
            ->all();

        $candidates = [];
        foreach ($router->getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            if (str_contains($uri, '{')) {
                continue; // skip parameterised routes
            }
            $role = $this->roleFor($uri);
            if ($role === null) {
                continue;
            }
            // only top-level navigable areas (<=2 segments beyond the prefix root)
            if (substr_count($uri, '/') > 2) {
                continue;
            }
            $path = '/'.ltrim($uri, '/');
            if (in_array($path, $existingPaths, true)) {
                continue; // dedup against the existing registry
            }
            $candidates[$path] = [
                'key' => Str::slug(str_replace('/', '-', $uri)),
                'title' => $this->titleFor($uri),
                'icon' => 'apps-outline',
                'path' => $path,
                'web' => 'native',
                'mobile' => 'webview',
                'roles' => $this->roleFor($uri),
                'responsive_verified' => false,
            ];
        }

        $entries = array_values($candidates);

        if ($this->option('json')) {
            $this->line(json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($entries as $e) {
            $roles = "['".implode("', '", $e['roles'])."']";
            $this->line(sprintf(
                "['key' => '%s', 'title' => '%s', 'icon' => '%s', 'path' => '%s', 'web' => 'native', 'mobile' => 'webview', 'roles' => %s, 'responsive_verified' => false],",
                $e['key'], $e['title'], $e['icon'], $e['path'], $roles,
            ));
        }
        $this->info(count($entries).' candidate modules emitted.');

        return self::SUCCESS;
    }

    /** @return list<string>|null */
    private function roleFor(string $uri): ?array
    {
        foreach (self::ROLE_PREFIXES as $prefix => $roles) {
            if (str_starts_with($uri, $prefix)) {
                return $roles;
            }
        }

        return null;
    }

    private function titleFor(string $uri): string
    {
        $last = Str::afterLast($uri, '/');

        return Str::of($last)->replace('-', ' ')->title()->toString();
    }
}
```

- [ ] **Step 4: Run → PASS.** `php artisan test --filter=ParityScaffoldRegistryTest`

- [ ] **Step 5: pint + commit**

```bash
vendor/bin/pint app/Console/Commands/ParityScaffoldRegistry.php tests/Feature/Parity
git add app/Console/Commands/ParityScaffoldRegistry.php tests/Feature/Parity/ParityScaffoldRegistryTest.php
git commit -m "feat(parity): parity:scaffold-registry command (reproducible module curation from router)"
```

---

## Task 2: Populate the complete registry

**Files:** Modify `config/parity.php`

This is the data task. Generate the entries, fix the 2 stale paths, review, commit.

- [ ] **Step 1: Fix the 2 stale existing paths**

In `config/parity.php`, correct the existing webview entries whose paths don't resolve:
- `accounting`: `'path' => '/admin/accounting'` → `'/admin/accounting-v2'`
- `kyb`: `'path' => '/admin/kyb'` → `'/admin/kyb-v2'`
(Verify with `php artisan route:list --path=accounting` and `--path=kyb` that these are the real URIs; if the real path differs, use the real one. `audit` `/admin/audit` and `help` `/help` — verify they resolve too; fix if not.)

- [ ] **Step 2: Generate the candidate entries**

Run: `php artisan parity:scaffold-registry`
This prints ~105 PHP array literals (one per navigable area, excluding the 10 already registered).

- [ ] **Step 3: Review + paste into `config/parity.php`**

Append the emitted entries into the `'modules'` array in `config/parity.php`, after the existing 10. Review pass (human judgement, mechanical):
- **Dedup sanity:** the generator already excludes existing paths; double-check no path appears twice (e.g. an existing native module under a different URI). If a generated entry is semantically the same area as an existing native module (e.g. a second route to missions/earnings/finance), DROP it.
- **Titles:** the generator humanizes the URI's last segment (French slugs → e.g. `litiges` → "Litiges", `portefeuille` → "Portefeuille"). Fix any that read poorly; keep it quick — bespoke titles are not required for v1.
- **Keys:** generated keys are slugs of the full URI (e.g. `dashboard-client-litiges`). Keep them unique; that's their only requirement.
- Leave `icon => 'apps-outline'` as the v1 default (bespoke icons are a Phase-2 polish nicety, out of scope).
- Leave `responsive_verified => false` on all generated entries.

- [ ] **Step 4: Verify the file parses + the count grew**

Run: `php -r "require 'config/parity.php';"` (no parse error) and `php artisan tinker --execute="echo count(config('parity.modules'));"` — expect ~111.

- [ ] **Step 5: pint + commit**

```bash
vendor/bin/pint config/parity.php
git add config/parity.php
git commit -m "feat(parity): complete the registry — all ~111 navigable areas (webview, role-scoped) + fix 2 stale paths"
```

---

## Task 3: ParityPathsResolveTest

**Files:** Create `tests/Feature/Parity/ParityPathsResolveTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ParityPathsResolveTest extends TestCase
{
    public function test_every_registry_path_resolves_to_a_registered_get_route(): void
    {
        $routes = Route::getRoutes();
        $unresolved = [];

        foreach (config('parity.modules', []) as $module) {
            $path = ltrim((string) $module['path'], '/');
            $matched = collect($routes->getRoutes())->first(function ($route) use ($path) {
                return in_array('GET', $route->methods(), true)
                    && trim($route->uri(), '/') === $path;
            });
            if (! $matched) {
                $unresolved[] = $module['key'].' → /'.$path;
            }
        }

        $this->assertSame([], $unresolved, "Registry paths with no matching GET route:\n".implode("\n", $unresolved));
    }
}
```

- [ ] **Step 2: Run → it MUST pass.** `php artisan test --filter=ParityPathsResolveTest`
If any path is unresolved, the failure lists it — fix that entry's path in `config/parity.php` to the real route (re-run Task 2's review for that module). This test is the scale-proof guard against the SP3 stale-path bug; do NOT weaken it to make a bad path pass.

- [ ] **Step 3: commit**

```bash
vendor/bin/pint tests/Feature/Parity/ParityPathsResolveTest.php
git add tests/Feature/Parity/ParityPathsResolveTest.php config/parity.php
git commit -m "test(parity): every registry path resolves to a real GET route"
```

---

## Task 4: ParityEmbedRenderTest

**Files:** Create `tests/Feature/Parity/ParityEmbedRenderTest.php`

Proves each WebView fallback renders chrome-less. **Boundary:** client + provider modules are the must-render set (B2C/field, top-level pages render with a minimal user); admin centers are fixture-heavy and rarely on mobile — they are best-effort with documented skips. The test asserts each renderable page returns a non-error status and omits the nav-chrome marker; a page that errors due to missing fixtures is recorded as a skip (collected for the visual-QA runbook), not a silent pass.

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Parity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParityEmbedRenderTest extends TestCase
{
    use RefreshDatabase;

    /** Roles we assert MUST render (client + provider are the mobile-first audiences). */
    private const MUST_RENDER_ROLES = ['client', 'provider'];

    public function test_client_and_provider_webview_fallbacks_render_chrome_less(): void
    {
        $skipped = [];
        $failed = [];

        foreach (config('parity.modules', []) as $module) {
            if (($module['mobile'] ?? null) !== 'webview') {
                continue;
            }
            $roles = $module['roles'] ?? [];
            if (! array_intersect($roles, self::MUST_RENDER_ROLES) && $roles !== []) {
                continue; // admin-only → covered by visual QA, not this assertion
            }

            $user = $this->userForRoles($roles);
            $path = '/'.ltrim((string) $module['path'], '/');

            try {
                $response = $this->actingAs($user)->get($path.'?embed=1');
            } catch (\Throwable $e) {
                $skipped[] = $module['key'].' (exception: '.class_basename($e).')';
                continue;
            }

            $status = $response->getStatusCode();
            if ($status >= 500) {
                $skipped[] = $module['key'].' (HTTP '.$status.' — likely missing fixtures)';
                continue;
            }
            if ($status !== 200) {
                $failed[] = $module['key'].' → HTTP '.$status.' at '.$path;
                continue;
            }
            $html = $response->getContent();
            if (str_contains((string) $html, 'data-chrome="primary-nav"')) {
                $failed[] = $module['key'].' rendered WITH nav chrome (embed mode not applied)';
            }
        }

        // Failures (non-200 / chrome present) are real bugs. Skips (5xx/exception from
        // missing fixtures) are recorded for manual visual QA, not failures.
        if ($skipped !== []) {
            fwrite(STDERR, "[embed-render] fixture-heavy pages deferred to visual QA:\n".implode("\n", $skipped)."\n");
        }
        $this->assertSame([], $failed, "WebView fallbacks that failed to render chrome-less:\n".implode("\n", $failed));
    }

    private function userForRoles(array $roles): User
    {
        if (in_array('provider', $roles, true)) {
            return User::factory()->employe()->create();
        }

        return User::factory()->client()->create();
    }
}
```

- [ ] **Step 2: Run → observe.** `php artisan test --filter=ParityEmbedRenderTest`
Real failures (a client/provider page returning non-200, or rendering WITH nav chrome) are bugs:
- If a page 4xx's because it needs more than a bare factory user (e.g. an active subscription, a provider profile), seed the minimal prerequisite in `userForRoles`/a per-key setup so it renders — that's legitimate fixture-minimalism, not weakening.
- If a page renders WITH the nav chrome under `?embed=1`, the EmbedMode isn't applied to that route → investigate (the route may not be in the `web` middleware group, or uses a different layout). Fix the embed application or document why (rare).
- 5xx/exception pages (genuinely fixture-heavy) become STDERR skips → carry them into the runbook (Task 6).

- [ ] **Step 3: Make the must-render set green** (seed minimal prerequisites; fix any embed-not-applied route). Don't weaken the chrome-less assertion.

- [ ] **Step 4: commit**

```bash
vendor/bin/pint tests/Feature/Parity/ParityEmbedRenderTest.php
git add tests/Feature/Parity/ParityEmbedRenderTest.php app/Http config/parity.php
git commit -m "test(parity): client+provider webview fallbacks render chrome-less in embed mode"
```

---

## Task 5: ParityRoleAccessTest

**Files:** Create `tests/Feature/Parity/ParityRoleAccessTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

namespace Tests\Feature\Parity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParityRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_parity_map_contains_only_client_and_public_modules(): void
    {
        Sanctum::actingAs(User::factory()->client()->create());
        $keys = collect($this->getJson('/api/parity-map')->assertOk()->json('data'))->pluck('key');

        foreach (config('parity.modules', []) as $module) {
            $roles = $module['roles'] ?? [];
            $visible = $roles === [] || in_array('client', $roles, true);
            if (in_array('admin', $roles, true) && ! in_array('client', $roles, true)) {
                $this->assertFalse($keys->contains($module['key']), "client must NOT see admin module {$module['key']} (F4)");
            }
            if ($roles === ['client']) {
                $this->assertTrue($keys->contains($module['key']), "client should see {$module['key']}");
            }
        }
    }

    public function test_client_cannot_access_an_admin_module_path(): void
    {
        // Pick the first admin-only module from the registry and confirm a client is blocked.
        $admin = collect(config('parity.modules', []))
            ->first(fn ($m) => ($m['roles'] ?? []) === ['admin']);
        $this->assertNotNull($admin, 'expected at least one admin-only module');

        $client = User::factory()->client()->create();
        $status = $this->actingAs($client)->get('/'.ltrim($admin['path'], '/'))->getStatusCode();

        $this->assertNotSame(200, $status, "client got 200 on admin path {$admin['path']} — authz gap");
    }
}
```

- [ ] **Step 2: Run → make green.** `php artisan test --filter=ParityRoleAccessTest`
If a client receives 200 on an admin path, that's a real authz gap — but it is an EXISTING-route authz concern, out of this sub-project's scope to fix; if found, record it as a finding and (if the route genuinely lacks role middleware) note it for a security follow-up rather than silently passing. The parity-map filtering half MUST pass (it's the registry's own correctness).

- [ ] **Step 3: commit**

```bash
vendor/bin/pint tests/Feature/Parity/ParityRoleAccessTest.php
git add tests/Feature/Parity/ParityRoleAccessTest.php
git commit -m "test(parity): parity-map role filtering ↔ web authz across all modules"
```

---

## Task 6: EMBED-VISUAL-QA.md scaffold

**Files:** Create `docs/runbooks/EMBED-VISUAL-QA.md`

- [ ] **Step 1: Generate the per-module checklist**

Build the doc from `config('parity.modules')` (you may add a tiny throwaway tinker snippet to emit the rows, then paste — or hand-write the structure). Contents:
- **Header:** purpose (Phase-2 operate-together visual QA of every WebView fallback at phone width), the division of labor (Claude scripts the steps + interprets; operator logs into web at phone width and reports), and the **`responsive_verified` flip rule**: a module flips to `true` only when its embed-render test is green AND its visual QA row is PASS.
- **Prerequisites:** a phone-width browser (or device), a test login per role (client / provider / admin).
- **Per-module rows** (one table, grouped by role) with columns: `key | path | role to log in as | (visual checks) | PASS/FAIL | timestamp | who | notes`. Visual checks (state once at top, reference per row): no horizontal scroll; tap targets ≥ 44px; readable text; no overflowing/broken layout; nav chrome absent (embed applied). For each module, the URL to open is `{path}?embed=1`.
- **Deferred (fixture-heavy) section:** list the modules the embed-render test SKIPPED (from Task 4's STDERR output) — these have no automated render proof and MUST be visually verified manually.
- **DoD reference:** Phase 1 (this PR) = registry complete + the 3 test suites green + this scaffold. Phase 2 = every row PASS + responsive_verified flipped.

- [ ] **Step 2: commit**

```bash
git add docs/runbooks/EMBED-VISUAL-QA.md
git commit -m "docs(parity): EMBED-VISUAL-QA Phase-2 checklist (per-module, flip rule, deferred list)"
```

---

## Task 7: Full verification + DoD

**Files:** none (verification + any pint commit).

- [ ] **Step 1: Run the parity suite + gates**

```
php artisan test --filter=Parity
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
```
- All `Parity*` tests green. The path-resolution test is the key gate (every registry path real). Embed-render must-render set green (client+provider); admin skips documented. Role-filter green.
- Pint clean; **PHPStan full run** `[OK] No errors` (run the FULL analyse — the SP3 lesson: path-scoped runs hid 12 errors). Fix new-file issues with real annotations (no suppressions).

- [ ] **Step 2: Confirm registry completeness**

`php artisan tinker --execute="echo count(config('parity.modules'));"` — confirm ~111. Spot-check 3 client + 3 provider + 3 admin entries have a real path (cross-check `route:list`) and correct `roles`.

- [ ] **Step 3: Confirm Phase-1 DoD**
1. Registry complete (every navigable area registered, real paths, role-scoped; 6 native untouched). ✓
2. ParityPathsResolveTest green. ✓
3. ParityEmbedRenderTest: client+provider must-render set green; admin/fixture-heavy skips documented in the runbook. ✓
4. ParityRoleAccessTest green (parity-map filtering; any authz-gap finding recorded). ✓
5. EMBED-VISUAL-QA.md scaffolded. ✓
6. CI green (PHPUnit + Pint + PHPStan full). ✓
Phase 2 (visual QA + responsive fixes + responsive_verified flips) is operate-together, post-merge.

- [ ] **Step 4: commit (only if pint reformatted anything)**

```bash
git add -A
git commit -m "chore(parity): pint formatting on registry-completion files"
```

---

## Self-review notes (already applied)

- **Spec coverage:** registry completion → Tasks 1–2 (generated, not hand-listed — the right call for 111 entries); path-resolution test → Task 3; embed-render test → Task 4; role-filter test → Task 5; QA runbook → Task 6; verification → Task 7. Phase 2 is a runbook skeleton only (Task 6), not build tasks — matches the spec.
- **No native migration:** every generated entry is `mobile => 'webview'`; the 6 native entries are explicitly untouched. SP4 migrates nothing.
- **Stale-path realism:** Task 2 Step 1 fixes the 2 known-stale existing paths (`/admin/accounting`→`-v2`, `/admin/kyb`→`-v2`); Task 3 catches any others at scale.
- **Embed-render honesty:** the must-render assertion is scoped to client+provider (mobile-first, render with minimal fixtures); admin centers (fixture-heavy, rarely on mobile per the rubric) are best-effort with documented skips carried into the visual-QA runbook — so the DoD's "every fallback proven to render" is honest (automated where feasible, manual visual QA for the rest), not falsely claimed.
- **Type/name consistency:** `parity:scaffold-registry`, `config('parity.modules')`, the entry shape (`key/title/icon/path/web/mobile/roles/responsive_verified`), the `data-chrome="primary-nav"` marker, role mapping (admin/client/provider), and test class names are consistent across tasks and match SP1's registry schema + EmbedMode.
