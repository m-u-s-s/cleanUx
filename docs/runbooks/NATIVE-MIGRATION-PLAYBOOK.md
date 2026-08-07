# Native-Migration Playbook — Brio Progressive Native Migration

**Status:** Living reference — update with lessons learned after each completed migration.  
**Source of truth for module routing:** `config/parity.php`  
**Prerequisite decision:** Before opening any implementation PR, score the module against `docs/runbooks/NATIVE-MIGRATION-RUBRIC.md`. Do not start this playbook if the verdict is **STAY WEBVIEW**.

---

## Overview

The seam is a single field in `config/parity.php`:

```php
'mobile' => 'webview'   // currently served via EmbeddedModuleRoute
'mobile' => 'native'    // routes to the registered Expo screen
```

Every module starts as `webview`. When its native screen achieves full feature parity, the flag flips. The WebView path is never removed — rollback is instant and zero-code. Until **both** the `NATIVE_ROUTES` entry **and** the parity flag exist, the module keeps rendering the WebView, so no mid-PR broken state is possible.

---

## The 8-Step Recipe

### Step 1 — Parity audit

Walk the web module at its `path` (listed in `config/parity.php`) and enumerate every visible action and view:

- Every list, detail, and form the user can reach.
- Every button, status transition, and destructive action.
- Every file the user can download or share.

Record these as checkboxes in a **parity checklist** (template in the next section). Copy the template into your PR description and into the module's inline documentation.

**The flag does NOT flip until every checklist item is marked complete.** Partial parity is not allowed — a partially-native screen that silently omits actions is worse than the WebView.

---

### Step 2 — API-gap analysis

The native screen consumes the existing JSON API via `apiClient` (imported from `@/api`). Web modules frequently act through server-rendered Livewire components that have **no corresponding API endpoint**. Each such action is an **API gap** — a backend endpoint that must be built before the native screen can call it.

For each parity checklist item:
1. Check whether a `GET`/`POST`/`PUT`/`DELETE` endpoint already exists (search routes/api.php and the relevant controller).
2. If none exists: add it to the **API gaps** section of the parity checklist.
3. Every new endpoint must:
   - Return JSON, authenticated via Sanctum.
   - Enforce ownership isolation (the requesting user may only access resources they own — verify with a cross-tenant test: see Step 6).
   - Be documented in the checklist so the PR reviewer can verify coverage.

> **Why this matters:** Low-tractability modules in the rubric score lower partly because many Livewire-only actions translate into many expensive API gaps. A module with five parity checklist items and four API gaps is a larger PR than a module with three items and one gap.

---

### Step 3 — Build the native screen(s)

Create one or more screen files under `mobile/client/src/screens/`. Naming convention: `<ModuleKey>Screen.tsx` (e.g. `InvoicesScreen.tsx`, `InvoiceDetailScreen.tsx`).

Requirements:
- Consume all endpoints identified in Step 2 via `apiClient`.
- Use the shared UI kit (`@/ui`) and design tokens (`@/theme`) — do not introduce inline colours or spacing values.
- Cover every item in the parity checklist, including the **device upgrades** identified by the rubric (native PDF share/download via the Share API, camera access, GPS, deep-link receipt, etc.).
- Keep each screen file focused on a single view — if the module has a list and a detail, they are two separate screen files and two separate navigation entries.

---

### Step 4 — Wire navigation (3 additive edits)

Three files change. All edits are **additive** — no existing lines are modified or removed.

**4a. `mobile/client/src/navigation/types.ts`**

Add the new screen name(s) and their params to `RootStackParamList`:

```ts
// Example
Invoices: undefined;
InvoiceDetail: { id: number };
```

**4b. `mobile/client/src/navigation/RootNavigator.tsx`**

Add an import for each new screen component, then register a `<Stack.Screen>` inside the authenticated block:

```tsx
// At the top of the file
import { InvoicesScreen } from '@/screens/InvoicesScreen';
import { InvoiceDetailScreen } from '@/screens/InvoiceDetailScreen';

// Inside the authenticated Stack.Navigator block
<Stack.Screen
  name="Invoices"
  component={InvoicesScreen}
  options={{ title: 'Factures', headerShown: true }}
/>
<Stack.Screen
  name="InvoiceDetail"
  component={InvoiceDetailScreen}
  options={{ title: 'Facture', headerShown: true }}
/>
```

**4c. `mobile/client/src/screens/ModuleHubScreen.tsx`**

Add a `NATIVE_ROUTES` entry for the module key (must exactly match the `key` field in `config/parity.php`):

```ts
const NATIVE_ROUTES: Record<string, { screen: string; params?: object }> = {
  booking:  { screen: 'BookingWizard' },
  tracking: { screen: 'MissionTracking' },
  chat:     { screen: 'ChatList' },
  invoices: { screen: 'Invoices' },   // ← new entry
};
```

> Until Step 5 flips the flag, this entry has no runtime effect — the parity map still returns `mobile: 'webview'` for this module, so `ModuleHubScreen` falls through to `EmbeddedModule`. This means the three navigation edits can land in the same PR as the screen, safely, before the flag flip.

---

### Step 5 — Flip the seam

Edit exactly one line in `config/parity.php`:

```php
// Before
['key' => 'invoices', ..., 'mobile' => 'webview', ...]

// After
['key' => 'invoices', ..., 'mobile' => 'native', ...]
```

This is the moment the module goes live as native. Because the `NATIVE_ROUTES` entry and the screen are already registered (Step 4), the routing resolves immediately. Because the WebView path (`EmbeddedModuleRoute`) is untouched, reverting is the inverse of this single edit.

**Only flip the flag when every parity checklist item is checked.**

---

### Step 6 — Tests

Three test categories are required before the PR is mergeable:

**6a. Native screen tests** (`mobile/client/src/screens/__tests__/<Module>Screen.test.tsx`)

- Mock `apiClient` (jest mock at `@/api`).
- Cover every checklist action: successful fetch renders the correct UI; every interactive action triggers the expected API call with the expected payload; error states are handled gracefully.

**6b. Routing flag-flip assertion**

Copy the pattern from `mobile/client/src/screens/__tests__/ParityFlagFlip.test.tsx`:

```ts
// webview flag → routes to EmbeddedModule
(parity.fetchParityMap as jest.Mock).mockResolvedValue([
  { key: 'invoices', title: 'Factures', icon: 'receipt-outline', path: '/client/invoices', mobile: 'webview' },
]);
// ... press → expect navigate('EmbeddedModule', { path: '/client/invoices', title: 'Factures' })

// native flag → routes to Invoices, NOT EmbeddedModule
(parity.fetchParityMap as jest.Mock).mockResolvedValue([
  { key: 'invoices', title: 'Factures', icon: 'receipt-outline', path: '/client/invoices', mobile: 'native' },
]);
// ... press → expect navigate('Invoices', undefined)
//             expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything())
```

**6c. Backend API tests** (for every new endpoint built in Step 2)

- Happy-path: authenticated user retrieves/acts on their own resource.
- Ownership isolation: authenticated user **cannot** retrieve or act on a resource owned by a different user (assert 403 or 404).
- For any endpoint that writes data: assert the correct DB state after the request.

---

### Step 7 — Safety and rollback

The WebView path (`EmbeddedModuleRoute`) is never deleted. Rollback is:

```php
// config/parity.php — one-line revert
'mobile' => 'native'   →   'mobile' => 'webview'
```

This is a config change. No code revert, no migration, no deploy pipeline beyond a config cache clear (`php artisan config:clear`). It can be applied in production in seconds.

**Optional: staged rollout**

For modules with high traffic or complex API gaps, gate the flag flip behind a per-module rollout flag (using the existing feature flag infrastructure). Set the rollout percentage to 5 %, monitor for crashes and API errors, then ramp to 100 % before removing the flag. The parity flag in `config/parity.php` remains the final state — the rollout flag is a temporary guard.

---

### Step 8 — Ship as one small PR

The PR for module X contains exactly:
1. New screen file(s) in `mobile/client/src/screens/`.
2. The three additive navigation edits (types, navigator, hub routes).
3. New or updated API endpoints (routes + controller methods).
4. Tests for all of the above.
5. The single-line flag flip in `config/parity.php`.

No other modules are touched. No existing screens are modified. The PR title follows the convention: `feat(native): migrate <key> module to native screen`.

---

## Parity-Checklist Template

Copy this block into the PR description and fill it in completely before requesting review.

```markdown
# Parity checklist — <module>
Web surface: <Livewire component / route>
Actions/views (full parity required):
- [ ] ...
API gaps (Livewire-only actions needing new endpoints):
- [ ] GET ...
Device upgrades (per rubric): <camera / GPS / native share / offline ...>
Rollback: flip config/parity.php <module> native→webview
```

---

## Worked Example — `invoices` Migration

The `invoices` module is the first worked example of this playbook. It was selected by the rubric (score 11/15, tractability 3 — all gates pass; see `NATIVE-MIGRATION-RUBRIC.md` for the full scoring worksheet) and exercises the **complete playbook** including the most expensive step: the API-gap build.

**What makes it a representative example:**

- **Non-trivial API gap.** The Livewire-rendered invoice list and detail had no JSON API counterpart. Step 2 required building two new endpoints (`GET /api/client/invoices` list and `GET /api/client/invoices/{id}` detail) before any native screen could be written. This is the typical cost pattern for long-tail webview modules.
- **Ownership isolation.** Each new endpoint scopes its query to `auth()->id()` and carries a cross-user isolation test (a second authenticated user must not be able to retrieve another user's invoice — asserted as 403/404). This pattern is required for every new endpoint in every migration.
- **Device upgrade.** The rubric awarded 2 points for device leverage specifically because the native share sheet enables reliable PDF download/share on iOS and Android — something the embedded WebView renders unreliably on iOS. The native `InvoiceDetailScreen` calls the platform Share API directly.
- **Three-file additive navigation.** The types file gained `Invoices: undefined` and `InvoiceDetail: { id: number }`; the navigator registered both screens in the authenticated block; the hub routes map gained `invoices: { screen: 'Invoices' }`.
- **Single-line flag flip.** After all checklist items were verified green, `config/parity.php` changed one token: `'webview'` → `'native'` on the `invoices` row.

Reviewing this PR alongside the playbook is the fastest way to internalise the pattern before starting a new migration.

---

## Quick Reference

| File | What changes |
|---|---|
| `config/parity.php` | Step 5: one-line flag flip (`webview → native`) |
| `mobile/client/src/screens/<Module>Screen.tsx` | Step 3: new screen file(s) |
| `mobile/client/src/navigation/types.ts` | Step 4a: add screen name + params to `RootStackParamList` |
| `mobile/client/src/navigation/RootNavigator.tsx` | Step 4b: import + `<Stack.Screen>` in authenticated block |
| `mobile/client/src/screens/ModuleHubScreen.tsx` | Step 4c: add `key: { screen: 'Name' }` to `NATIVE_ROUTES` |
| `mobile/client/src/screens/__tests__/<Module>Screen.test.tsx` | Step 6a: screen unit tests |
| `mobile/client/src/screens/__tests__/ParityFlagFlip.test.tsx` | Step 6b: reuse/extend flag-flip routing assertion |
| `routes/api.php` + controller | Step 2/6c: new JSON endpoints + ownership-isolation tests |

---

*Last updated: 2026-05-30 — initial playbook, aligned with `config/parity.php` and `NATIVE-MIGRATION-RUBRIC.md` at branch `feat/native-migration`.*
