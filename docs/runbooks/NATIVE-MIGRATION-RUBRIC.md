# Native-Worthiness Rubric — CleanUx Progressive Native Migration

**Status:** Living reference — revisit scored modules with real usage data post-launch.  
**Source of truth for module registry:** `config/parity.php`  
**Branch convention:** flip `mobile` from `'webview'` to `'native'` in `config/parity.php` when a module is promoted; no other code changes required.

---

## 1. The Rubric (Scoring Table)

Each criterion is scored **0–3**. Maximum possible score: **15**.

| Criterion | Score 0 | Score 3 | Why it matters |
|---|---|---|---|
| **Frequency** | Rarely opened on mobile (once a month or less) | Daily-driver (opened multiple times per day) | Native investment pays off where users actually live; low-frequency screens don't justify the build cost. |
| **Device leverage** | None — no hardware capability needed beyond a browser | Several capabilities: camera/QR scan, GPS, push deep-link, biometric auth, offline mode, native share/download | These are things a WebView genuinely cannot do well or requires painful workarounds to approximate. |
| **WebView friction** | Read-only content; no interaction required | Heavy forms, fast nav between sub-pages, or latency-sensitive interactions | Embedded WebViews feel worst when users need speed and responsiveness; static content is fine in a WebView. |
| **Audience fit** | Admin back-office (desktop-first users, rarely on mobile) | B2C client or field provider on the go | Native UX investment benefits mobile-first users far more than back-office operators who work at a desk. |
| **Full-parity tractability** | Huge multi-tab admin center (unbounded surface, years of work) | Small, bounded feature set (list + detail + one action = done) | This is the inverse-cost gate: the more tractable the surface, the more viable a faithful native port is. |

> **Scoring guidance:**
> - Score 0 = the worst case for that criterion (no benefit / maximum cost).
> - Score 3 = the best case (maximum benefit / minimum cost).
> - Scores 1 and 2 are intermediate — use judgment and documented rationale.

---

## 2. Gates & Threshold

### Hard gate — Tractability

**Never migrate if `tractability ≤ 1`.**

Replicating an unbounded surface in full native parity is the multi-year trap. A score of ≤ 1 on tractability means the feature surface cannot be faithfully bounded in a single sprint-sized PR. No other score can compensate for this.

### Disqualifiers — Auto stay-WebView

The following module types stay WebView regardless of total score:

- **Export-heavy modules** — any module whose primary value is generating FEC, CSV, IIF, or other file exports (e.g., accounting). Native apps are poor delivery vehicles for file exports; the web is better.
- **Admin-only + Frequency 0** — admin modules that are essentially never opened on mobile have negative ROI for native migration. Keep them in WebView where the full desktop-class UI is available.
- **Unbounded feature surface** — any module whose complete feature set cannot be described in a short list. If you cannot enumerate every screen in under five bullet points, it is not tractable.

### Migration threshold

| Condition | Action |
|---|---|
| `total score ≥ 9` AND `tractability ≥ 2` | **MIGRATE** — schedule native implementation. |
| `total score ≥ 9` BUT `tractability < 2` | **STAY WEBVIEW** — hard gate applies; revisit only if surface is split into bounded sub-features. |
| `total score < 9` | **STAY WEBVIEW** — revisit with real usage data post-launch. You cannot honestly rank frequency until the app is live and telemetry is collected. |
| Any disqualifier applies | **STAY WEBVIEW** — disqualifiers are non-negotiable regardless of score. |

> **Note on the frequency criterion:** Pre-launch scores for frequency are estimates. All stay-WebView decisions based on low frequency should be re-evaluated once you have at least 30 days of real in-app session data.

---

## 3. Per-Module Scoring Worksheet (Template)

Use this template when evaluating a new module for migration candidacy.

```
## Module: <key from config/parity.php>

**Title:** <human label>
**Path:** <web path>
**Roles:** <roles array>
**Evaluated by:** <author>
**Date:** <YYYY-MM-DD>

### Scores

| Criterion         | Score (0–3) | Rationale |
|-------------------|-------------|-----------|
| Frequency         |             |           |
| Device leverage   |             |           |
| WebView friction  |             |           |
| Audience fit      |             |           |
| Tractability      |             |           |
| **TOTAL**         | **  / 15**  |           |

### Gate checks

- [ ] Tractability ≥ 2? (Hard gate — if NO, stop here: STAY WEBVIEW)
- [ ] Export-heavy disqualifier applies? (If YES, stop here: STAY WEBVIEW)
- [ ] Admin-only + Frequency 0 disqualifier applies? (If YES, stop here: STAY WEBVIEW)
- [ ] Unbounded surface disqualifier applies? (If YES, stop here: STAY WEBVIEW)

### Verdict

**[ ] MIGRATE** / **[ ] STAY WEBVIEW**

### Notes

<Any additional context, assumptions, or conditions for revisiting this decision.>
```

---

## 4. Ranked Backlog

### Already-native modules — no action needed

These modules were built as native Expo screens and are excluded from scoring.

| Key | Title | Roles | Status |
|---|---|---|---|
| `booking` | Réserver | client | N/A (already native) |
| `tracking` | Suivi | client | N/A (already native) |
| `chat` | Messages | all authenticated | N/A (already native) |
| `missions` | Missions | provider | N/A (already native) |
| `earnings` | Revenus | provider | N/A (already native) |

---

### WebView modules — scored

Modules sourced from `config/parity.php` where `mobile = 'webview'`, scored against the rubric above.

| Module | Frequency | Device leverage | WebView friction | Audience fit | Tractability | **Total** | Gate checks | **Verdict** |
|---|:---:|:---:|:---:|:---:|:---:|:---:|---|---|
| `invoices` (Factures) | 2 | 2 | 1 | 3 | 3 | **11** | Score ≥ 9, tractability ≥ 2 — all gates pass | **MIGRATE** |
| `kyb` (KYB) | 0 | 1 | 1 | 0 | 1 | **3** | Tractability ≤ 1 hard gate + admin-only disqualifier | **STAY WEBVIEW** |
| `audit` (Audit) | 0 | 0 | 1 | 0 | 1 | **2** | Tractability ≤ 1 hard gate + admin-only disqualifier | **STAY WEBVIEW** |
| `accounting` (Comptabilité) | 0 | 0 | 2 | 0 | 0 | **2** | Export-heavy disqualifier + tractability ≤ 1 hard gate + admin-only disqualifier | **STAY WEBVIEW** |
| `help` (Aide) | 1 | 0 | 0 | 2 | 2 | **5** | Score < 9 threshold | **STAY WEBVIEW** |

---

### Score rationale — detail

#### `invoices` — Score 11 → MIGRATE (first native migration exemplar)

| Criterion | Score | Rationale |
|---|:---:|---|
| Frequency | 2 | Clients consult invoices after each completed booking; moderate regular use. |
| Device leverage | 2 | PDF share/download via native share sheet; deep-link from push notification into invoice detail. |
| WebView friction | 1 | Mostly read-only list + detail, but PDF rendering in WebView on iOS is unreliable. |
| Audience fit | 3 | B2C clients on mobile — exactly the audience that benefits from native UX. |
| Tractability | 3 | Fully bounded: list of invoices, invoice detail, download/share PDF. Three screens. |
| **Total** | **11** | Threshold ≥ 9, tractability ≥ 2 — all gates pass. **Schedule for native.** |

#### `help` — Score 5 → STAY WEBVIEW

| Criterion | Score | Rationale |
|---|:---:|---|
| Frequency | 1 | FAQ is consulted infrequently; mostly at onboarding. |
| Device leverage | 0 | Content-only; no hardware capability needed. |
| WebView friction | 0 | Static read-only FAQ; a WebView is an ideal delivery vehicle. |
| Audience fit | 2 | Public-facing, but content-only pages have no UX gap between web and native. |
| Tractability | 2 | Technically bounded, but there is no native value to add. |
| **Total** | **5** | Below threshold. WebView is fine. Revisit if interactive help (chat support, search) is added. |

#### `accounting` — Score 2 → STAY WEBVIEW

| Criterion | Score | Rationale |
|---|:---:|---|
| Frequency | 0 | Admin-only; accountants work at a desktop, not on a phone. |
| Device leverage | 0 | No hardware capability required. |
| WebView friction | 2 | Multi-tab admin center with complex table UI, but this is a desktop-first tool — friction is acceptable and the web handles it well. |
| Audience fit | 0 | Admin back-office users. |
| Tractability | 0 | Huge multi-tab center: ledger, FEC export, Sage/QuickBooks export, period closer, reconciliation. Unbounded surface. |
| **Total** | **2** | Export-heavy disqualifier (FEC/CSV/IIF exports) + tractability ≤ 1 hard gate + admin-only disqualifier. Three independent reasons to stay WebView permanently. |

#### `audit` — Score 2 → STAY WEBVIEW

| Criterion | Score | Rationale |
|---|:---:|---|
| Frequency | 0 | Admin-only compliance review; opened rarely and reactively. |
| Device leverage | 0 | No hardware capability required. |
| WebView friction | 1 | Read-heavy with some filtering; tolerable in WebView. |
| Audience fit | 0 | Admin back-office users only. |
| Tractability | 1 | Large event log with filtering, PII redaction config, CSV/JSON export, retention rules — difficult to bound. |
| **Total** | **2** | Tractability ≤ 1 hard gate + admin-only disqualifier. |

#### `kyb` — Score 3 → STAY WEBVIEW

| Criterion | Score | Rationale |
|---|:---:|---|
| Frequency | 0 | Admin-only B2B compliance workflow; opened only when onboarding new business clients. |
| Device leverage | 1 | Could theoretically use camera for document capture, but this flow is done at a desk. |
| WebView friction | 1 | Multi-step forms, but the admin who runs KYB operates from a desktop. |
| Audience fit | 0 | Admin back-office users. |
| Tractability | 1 | 4-tab center (entities, documents, verifications, sanctions, beneficial owners) — difficult to bound. |
| **Total** | **3** | Tractability ≤ 1 hard gate + admin-only disqualifier. |

---

### Post-launch revisit candidates

The three admin modules (`accounting`, `audit`, `kyb`) and `help` are all **STAY WEBVIEW** under current estimates. They should be re-evaluated under the following conditions:

- **Frequency criterion:** After 30+ days of live app telemetry — if a module registers unexpectedly high mobile session counts, rescore it.
- **Surface changes:** If `accounting` is split into a bounded "my earnings summary" view for providers, that sub-feature may score differently than the full admin center.
- **New device capabilities:** If a future sprint adds biometric-gated invoice access or provider document upload via camera to KYB, rescore the affected modules.

The re-evaluation process: fill in a new scoring worksheet (Section 3), record the date and author, and update this backlog table with the new score and verdict.

---

*Last updated: 2026-05-30 — initial rubric, scored against `config/parity.php` at branch `feat/native-migration`.*
