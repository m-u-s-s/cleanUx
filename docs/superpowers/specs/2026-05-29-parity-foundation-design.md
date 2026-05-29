# Parity Foundation — Design Spec

**Date:** 2026-05-29
**Status:** Approved design (pre-implementation-plan)
**Sub-project:** 1 of 4 in the "Total channel parity → launch" program

---

## Context & goal

**The driving goal** is to launch CleanUx to real users (both web B2B and mobile B2C
audiences) with a single product principle: **total channel parity** — every module,
for every role, usable on both mobile and web, so the user picks whichever surface they
prefer. The long-term intent is that the entire surface becomes native on mobile
*eventually*, migrated progressively.

This reverses the previously-saved strategy ("Web = B2B, Mobile = B2C, no redundancy").
The reversal is deliberate.

### Current state (verified 2026-05-29)

| Surface | Footprint |
|---|---|
| Web | 217 Livewire components, 539 Blade views, ~2,874 LOC of API routes — effectively all 50 modules |
| Mobile client (Expo) | ~39 files / 30 screens — booking, payment, loyalty, ratings, tracking, phone |
| Mobile provider (Expo) | ~35 files / 35 screens — missions, earnings, inspection, presence, tracking |

- Mobile auth = **Sanctum bearer tokens** (`/api/auth/login|refresh|me|logout`, token-grace period).
- Web auth = **session cookies** (Livewire/Blade). The two auth worlds do not currently talk.
- **No WebView** exists in either Expo app today.
- Existing infra to build on: a `shared/` lib across both Expo apps, offline queue + cache,
  and a PWA scaffold (`public/sw.js`, `resources/js/pwa.js`, `manifest`).

The mobile surface covers only the **hot operational paths** (~10–12% of the web surface).
Closing the gap to "every module on both" by rebuilding ~150 screens natively is a
multi-year effort — so we do **not** do that. Parity is achieved as an architecture
property, not a brute-force rebuild.

### Program decomposition (for reference)

The full program is too large for one implementation plan. It is sequenced as:

1. **Parity Foundation** *(this spec)* — make "every module on both surfaces" true now.
2. **Launch hardening of the transaction spine** — staging, real payment/tracking/webhook
   validation, E2E of book→pay→execute→settle, security isolation, monitoring/backups.
3. **Progressive native migration** — strangler queue replacing embedded-web screens with
   native ones, one safe PR at a time.
4. **Long-tail polish + back-office native** — opportunistic, lowest priority.

Launch rides on the strength of sub-projects 1 + 2; 3 and 4 run continuously post-launch
without blocking it.

---

## Approach (chosen: API-first + hybrid mobile, progressively native)

**The principle: the WebView is a renderer, not a new app.** Every embedded screen is an
existing, already-authorized web page shown inside the native shell. This means **zero new
business logic and zero new authorization surface on mobile** — all 50 modules' server-side
role checks, validation, and behavior apply unchanged. That is what makes "every module on
mobile" both safe and near-free.

Three moving parts deliver it:

1. **Auth/session bridge** — converts a mobile Sanctum token into a live, disposable web
   session so embedded pages load already-logged-in, invisibly.
2. **Parity registry** — single source of truth mapping every module to its delivery mode
   per surface. Mobile navigation is built from it. Progressive migration = flipping a flag.
3. **WebView host + web "embed mode"** — a native component that renders any web route
   stripped of web chrome; the same embed/responsiveness work also makes the **web itself**
   a full mobile-capable PWA option.

Alternatives considered and rejected:
- **PWA-only / thin wrapper (Capacitor):** lowest effort but limited native feel/offline,
  app-store rejection risk, and discards existing native Expo investment.
- **Full native parity:** best UX but multi-year, triple-codebase, duplicated business
  logic — incompatible with launching soon.

---

## Components & boundaries

Six units, each single-purpose, with a defined interface and explicit dependencies. None
couple to business logic — they are all plumbing.

### Backend (Laravel)

**1. `WebViewTicketService`** — issues/validates handoff tickets.
- Interface: `issue(User $u, string $deviceId, string $targetPath): SignedTicket`;
  `redeem(string $ticket): ?User`.
- Properties: single-use (consumed atomically), ~60s TTL, HMAC-signed, bound to user id +
  Sanctum token id + device id.
- Depends on: app key (signing), cache/DB (single-use tracking). Fully unit-testable.

**2. `WebViewAuthController` + `auth.webview` flow** — two endpoints:
- `POST /api/auth/webview-ticket` (Sanctum-guarded) → `{ url }`.
- `GET /m/enter?ticket=…` (web/session guard) → redeems ticket (single-use), establishes
  session cookie, 302 → `<targetPath>?embed=1`. Rate-limited; ticket-only (no password path).
- Depends on: `WebViewTicketService`, web session guard, Sanctum. Thin controller.

**3. `EmbedLayout` + `embed` middleware** — Blade layout variant dropping nav/sidebar/footer;
middleware selects it on `?embed=1` (or header).
- Pure presentation. Controllers are agnostic to it. Depends on nothing.
- This is also where the **responsiveness pass** lives: the 217 Livewire components must
  reflow on narrow viewports in embed mode (and for the mobile-browser PWA path).

### Shared / config

**4. Parity registry** — `config/parity.php`:
`['module' => ['key','title','icon','web'=>'native','mobile'=>'webview|native','path','roles']]`
for all 50 modules.
- Interface: read by the mobile nav builder + exposed via `GET /api/parity-map`.
- The **single seam** Sub-project 3 mutates (flip `mobile: webview → native`).
- File-based (version-controlled); a flag-flip rides with the native code deploy.

### Mobile (Expo — placed in `shared/` so both apps reuse it)

**5. `EmbeddedModuleScreen`** — the WebView host (engine: `react-native-webview`).
- Interface: `<EmbeddedModuleScreen path="/admin/audit" title="Audit" />`.
- Internally: fetch ticket → load URL → inject bridge JS → render with native header/back.
- Depends on: `react-native-webview`, auth-bridge endpoint, `webBridge`, offline cache.
  Mockable in jest.

**6. `webBridge`** — the native↔web message contract (injected JS + RN handler).
- Enumerated message types (not arbitrary RPC): `ready`, `requestBack`, `openNative(route)`,
  `sessionExpired`, `error`.
- Depends on: `EmbeddedModuleScreen`, native nav + auth context. Pure message-passing.

### Dependency graph (clean line, no cycles)

```
Parity registry → mobile nav → { native screens | EmbeddedModuleScreen }
EmbeddedModuleScreen → webBridge + WebViewAuthController/WebViewTicketService → EmbedLayout pages
```

---

## Data flow

**A. Cold start → nav from parity**
App restores Sanctum token → `GET /api/parity-map` → nav builder routes `native` modules to
existing native screens and everything else to `EmbeddedModuleScreen`. Map cached offline so
the menu survives a cold offline launch.

**B. Native module tap** — unchanged: native screen, native API calls, offline queue.

**C. Long-tail module tap**
1. `EmbeddedModuleScreen` → `POST /api/auth/webview-ticket { deviceId, targetPath }`.
2. Server → `{ url: '…/m/enter?ticket=…' }`.
3. WebView loads it → server redeems (single-use), sets session cookie, 302 → `<path>?embed=1`.
4. Page renders chrome-less via `EmbedLayout`; RN draws native header + back.
5. Bridge fires `ready`; in-page navigation stays in the session (no per-click ticket).

**D. Cross-surface handoff** — an embedded page may emit `openNative('/booking/new')` → RN
closes the WebView and pushes the native screen. This is the migration on-ramp for
Sub-project 3.

**E. Session lifecycle** — Sanctum token is the source of truth; the web session is a
derived, disposable projection.
- `401`/`sessionExpired` in WebView → bridge → RN silently re-tickets from the live Sanctum
  token → reload. One automatic retry; on repeat failure → native logout.
- Invalid/expired Sanctum token (refresh fails) → global native logout; WebView sessions
  abandoned.
- Native logout → `/api/auth/logout` + clear WebView cookies (no orphaned web session).

**Invariant:** the user authenticates once (native); the web session is always a short-lived
projection of that. There is never a separate "log into the website" step.

---

## Error handling

- **WebView offline / network drop:** detect via bridge `error` + RN `onError`/`onHttpError`
  → native offline/error state with Retry; never a raw browser error page. Native modules
  keep working via the existing offline queue.
- **Ticket expired/replayed:** redemption fails closed → tiny "session expired" page posts
  `sessionExpired` → silent re-handoff; if it fails again → native logout.
- **Web 500 inside embed:** non-200 document load → native error screen with Retry + report
  (ties into existing error reporting).
- **Role-forbidden embedded route:** server's existing authz returns 403, rendered
  chrome-less as a clean "not available" state. No special-casing.

---

## Security

- **Ticket:** HMAC-signed, single-use (atomic consume), ~60s TTL, bound to user id + Sanctum
  token id + device id. A leaked ticket is useless after one load or one minute.
- **No new authorization surface:** every embedded page enforces the same server-side
  role/permission checks as for web users. The WebView cannot reach anything the user
  couldn't reach in a desktop browser. *Core safety claim — parity-by-WebView does not widen
  the attack surface.*
- **Cookie scoping:** handoff session cookie is `HttpOnly`, `Secure`, `SameSite`.
- **Bridge:** fixed, enumerated message protocol; no arbitrary JS eval; no dynamic
  `injectedJavaScript` input.
- **`/m/enter` hardening:** rate-limited, ticket-only, no password path; `embed=1` exposes
  nothing a normal session wouldn't.
- **Logout completeness:** native logout clears Sanctum token + WebView cookies + calls
  server logout. No orphaned sessions on shared devices.

---

## Testing

- **Backend (PHPUnit feature):** ticket issue→redeem→session established; single-use enforced
  (second redeem fails); TTL expiry; device/token binding; `embed=1` strips chrome; embedded
  route still 403s for the wrong role. Pure server tests, no device needed.
- **Mobile (jest):** parity-map → nav routing (native vs webview branch);
  `EmbeddedModuleScreen` ticket-fetch + load lifecycle (WebView mocked); bridge protocol
  (`requestBack`, `openNative`, `sessionExpired`, `error`); offline → native error state.
- **One real E2E** (existing `mobile/e2e` harness): launch → token → open native module →
  open embedded module authenticated → trigger `sessionExpired` → silent re-handoff.
- **No-skip discipline:** all runnable on SQLite + mocked WebView; they join the always-green
  suite and add nothing to the skipped-test debt.

---

## Scope boundaries

**In scope:** the six units above; wiring existing native hot-path screens into the
registry-driven nav; the embed-mode responsiveness pass; making the web an installable PWA
full mobile option.

**Explicitly out of scope** (each is a later sub-project or already done):
- New business modules or features.
- Native rebuilds of long-tail screens (Sub-project 3).
- Changes to existing native hot-path screen *internals* beyond nav wiring.
- Launch hardening — staging, payment/tracking/webhook prod validation, monitoring, backups
  (Sub-project 2).

---

## Definition of done

1. From a fresh install, after one native login, the user can reach **every** module on
   mobile — native where flagged, embedded-web otherwise — with no second login.
2. Embedded pages render chrome-less and role-correct; forbidden routes show a clean state.
3. Session expiry inside a WebView recovers silently; native logout leaves no orphaned
   session.
4. The web app is responsive in embed mode and installable as a PWA (full mobile option).
5. Backend + mobile test suites above are green and added to the always-run (no-skip) suites.
6. Flipping a module's `mobile` flag `webview → native` in `config/parity.php` re-routes its
   nav entry with no other code change (validates the Sub-project 3 seam).
