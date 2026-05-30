# Progressive Native Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Establish a repeatable native-migration pattern (rubric + playbook) and prove it by migrating the `invoices` module to full native parity — including building the missing client invoices API behind a shared, isolation-safe ownership scope.

**Architecture:** The parity registry (`config/parity.php`) is the seam: a module is `webview` until its native screen + `NATIVE_ROUTES` entry + flag flip exist, then it routes native (rollback = flip the flag back). The client finance ownership scope (complex enterprise/multi-site logic) is **extracted once** into a shared unit reused by both the existing Livewire component and the new API, so the two can never drift into a cross-tenant leak.

**Tech Stack:** Laravel 10 (Sanctum, Eloquent, DomPDF), PHPUnit; Expo/React Native (TypeScript), `@cleanux/shared` (`apiClient`), `expo-sharing`/`expo-file-system` for native PDF, Jest.

**Spec:** `docs/superpowers/specs/2026-05-30-progressive-native-migration-design.md`
**Branch:** `feat/native-migration` (off `feat/parity-foundation`). The parity foundation (`config/parity.php`, `ModuleHubScreen`, `EmbeddedModuleScreen`, `fetchParityMap`, `@/webview`, `@/parity`, jest config) is present on this base.

---

## Verified ground truth

- Web surface of the `invoices` module: `app/Livewire/Client/FinanceDocumentsClient.php`, mounted at route name `client.finance` → `GET /client/finance`. **Read-only** for the client (filters only). Shows quotes + invoices + finance summary + payment health.
- PDF download exists: `FinanceDocumentDownloadController@invoice` at `GET /client/finance/factures/{invoice}/telecharger` (name in `routes/client.php:184`).
- Ownership scope: `FinanceDocumentsClient::applyClientScope(Builder, relationPath='rendezVous')` — scopes to `client_id = user->id` OR (`organization_account_id = user.org` with optional site-scope via `EnterpriseRoutingService::allowedSiteIdsForUser` + `whereHas(rendezVous, organization_site_id IN allowed)`). **This is the isolation-critical logic to extract, not duplicate.**
- `FinanceInvoice`: `client_id`, `organization_account_id`, `status`, `rendez_vous_id` (Booking), `finance_quote_id`; relations `payments`, `reminders`, `rendezVous`; computed `effectiveStatus` (paid/partial/overdue) via the model.
- `config/parity.php` `invoices` entry: `['key'=>'invoices', 'path'=>'/client/invoices', 'mobile'=>'webview', 'roles'=>['client'], ...]` — the `path` is stale vs the real `/client/finance`; the audit reconciles it.
- Mobile: no native invoices screen yet. `NATIVE_ROUTES` in `ModuleHubScreen` currently has booking/tracking/chat. Sub-project 1's `ParityFlagFlip.test.tsx` is the flag-flip pattern to reuse.

---

## File structure

**Docs**
- Create `docs/runbooks/NATIVE-MIGRATION-RUBRIC.md` — the rubric + scoring worksheet + ranked backlog. (Task 1)
- Create `docs/runbooks/NATIVE-MIGRATION-PLAYBOOK.md` — the 8-step recipe + parity-checklist template. (Task 2)
- Create `docs/runbooks/parity-checklists/invoices.md` — the completed invoices audit/checklist. (Task 3)

**Backend (Laravel)**
- Create `app/Support/Finance/ClientFinanceDocumentScope.php` — the extracted ownership scope. (Task 4)
- Modify `app/Livewire/Client/FinanceDocumentsClient.php` — delegate `applyClientScope` to the shared unit. (Task 4)
- Create `app/Http/Controllers/Api/Client/InvoiceApiController.php` — `index`/`show`/`download`. (Tasks 5–7)
- Create `app/Http/Resources/InvoiceResource.php` — API serialization. (Task 5)
- Modify `routes/api/client.php` — register the 3 invoice routes. (Tasks 5–7)
- Tests: `tests/Unit/Finance/ClientFinanceDocumentScopeTest.php`, `tests/Feature/Api/Client/InvoiceApiTest.php`.

**Mobile (`mobile/client`)**
- Create `mobile/shared/src/finance/useInvoices.ts` — `fetchInvoices`/`fetchInvoice`/`invoicePdfUrl`. (Task 8)
- Create `mobile/client/src/screens/InvoicesScreen.tsx` (list + filters + summary). (Task 9)
- Create `mobile/client/src/screens/InvoiceDetailScreen.tsx` (detail + native PDF share). (Task 10)
- Modify `navigation/types.ts`, `navigation/RootNavigator.tsx`, `screens/ModuleHubScreen.tsx`. (Task 11)
- Modify `config/parity.php` (flip flag). (Task 12)
- Tests in `mobile/shared/src/finance/__tests__/` and `mobile/client/src/screens/__tests__/`.

---

## Task 1: Rubric doc + ranked backlog

**Files:** Create `docs/runbooks/NATIVE-MIGRATION-RUBRIC.md`

- [ ] **Step 1: Write the rubric doc**

Write the doc with: the 5-criterion scoring table (Frequency, Device leverage, WebView friction, Audience fit, Full-parity tractability — each 0–3, max 15) exactly as in the spec; the hard gate (`tractability ≤ 1` ⇒ never migrate); the disqualifiers (export-heavy, admin-only+freq-0, unbounded surface); the threshold (`score ≥ 9 AND tractability ≥ 2`); and a per-module scoring worksheet template.

Then apply it to every module in `config/parity.php` and produce the **ranked backlog** table (module | the 5 scores | total | verdict migrate/stay-WebView | note). Use the spec's worked numbers: invoices ~11 → migrate; help ~5 → stay; accounting/audit/kyb ~2 + disqualifier → stay. Score booking/tracking/chat/missions/earnings as already-native (N/A). State explicitly that low-scorers are revisited with real usage data post-launch.

- [ ] **Step 2: Commit**

```bash
git add docs/runbooks/NATIVE-MIGRATION-RUBRIC.md
git commit -m "docs(migration): native-worthiness rubric + ranked backlog"
```

---

## Task 2: Migration playbook doc

**Files:** Create `docs/runbooks/NATIVE-MIGRATION-PLAYBOOK.md`

- [ ] **Step 1: Write the playbook**

Document the 8-step recipe verbatim from the spec (Parity audit → API-gap analysis → Build native screen → Wire navigation [types + RootNavigator + NATIVE_ROUTES] → Flip the flag → Tests → Safety/rollback → Ship one small PR), with the exact file paths (`mobile/client/src/navigation/types.ts`, `RootNavigator.tsx`, `screens/ModuleHubScreen.tsx`, `config/parity.php`). Include a copy-paste **parity-checklist template**:

```markdown
# Parity checklist — <module>
Web surface: <Livewire component / route>
- [ ] Action/view 1
- [ ] Action/view 2
API gaps (Livewire-only actions needing endpoints):
- [ ] GET ...
Device upgrades (rubric): <camera/GPS/share/offline...>
Rollback: flip config/parity.php <module> native→webview
```

Reference the invoices migration (this plan) as the worked example.

- [ ] **Step 2: Commit**

```bash
git add docs/runbooks/NATIVE-MIGRATION-PLAYBOOK.md
git commit -m "docs(migration): repeatable native-migration playbook + checklist template"
```

---

## Task 3: Invoices parity audit (produces the checklist)

**Files:** Create `docs/runbooks/parity-checklists/invoices.md`

This task is DISCOVERY — read the real component and produce the committed checklist that scopes Tasks 5–10.

- [ ] **Step 1: Audit `FinanceDocumentsClient`**

Read `app/Livewire/Client/FinanceDocumentsClient.php` in full + its Blade view (find via `php artisan` route or `grep -rl finance-documents resources/views`). Enumerate, for the **invoices** half (scope quotes OUT — they're a separate future module; note this in the checklist):
- list invoices with filters: `status` (read `getStatusOptionsProperty`), `search`, `sort` (read `getSortOptionsProperty`);
- the finance summary widgets relevant to invoices (`getFinanceSummaryProperty`, `getPaymentHealthProperty`);
- per-invoice data shown: number, amount, `effectiveStatus`, due date, payments, reminders;
- PDF download (`FinanceDocumentDownloadController@invoice`).

Write `docs/runbooks/parity-checklists/invoices.md` using the template, listing the exact action set found + the API gaps (list/detail/PDF — all gaps, no client invoice API exists). Mark the quotes-out-of-scope decision explicitly.

- [ ] **Step 2: Commit**

```bash
git add docs/runbooks/parity-checklists/invoices.md
git commit -m "docs(migration): invoices full-parity audit checklist"
```

---

## Task 4: Extract the client finance ownership scope (isolation-safe, no duplication)

**Files:**
- Create: `app/Support/Finance/ClientFinanceDocumentScope.php`
- Modify: `app/Livewire/Client/FinanceDocumentsClient.php`
- Test: `tests/Unit/Finance/ClientFinanceDocumentScopeTest.php`

The API (Task 5) MUST reuse the exact same scope as the Livewire component, or a cross-tenant leak is likely. Extract it once.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Finance;

use App\Models\FinanceInvoice;
use App\Models\User;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientFinanceDocumentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_scopes_to_the_users_own_invoices_only(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $mine = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        $theirs = FinanceInvoice::factory()->create(['client_id' => $other->id]);

        $rows = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $me)->pluck('id');

        $this->assertTrue($rows->contains($mine->id));
        $this->assertFalse($rows->contains($theirs->id), 'must not include another client\'s invoice');
    }
}
```

NOTE: if `FinanceInvoice` has no factory, create a minimal one or build rows with `FinanceInvoice::create([...])` using the real required columns (read the model `$fillable` + migration). Report what you used.

- [ ] **Step 2: Run → FAIL** (`ClientFinanceDocumentScope` not found). `php artisan test --filter=ClientFinanceDocumentScopeTest`

- [ ] **Step 3: Extract the scope**

Read the FULL `FinanceDocumentsClient::applyClientScope` (lines ~50–90) and `allowedSiteIdsForCurrentUser`. Create `app/Support/Finance/ClientFinanceDocumentScope.php` with a static `apply(Builder $query, User $user, string $relationPath = 'rendezVous'): Builder` that contains the SAME logic (client_id OR org+site-scope via `EnterpriseRoutingService::allowedSiteIdsForUser` + the `whereHas` on `organization_site_id`). Preserve behavior exactly.

Then modify `FinanceDocumentsClient::applyClientScope` to delegate:
```php
protected function applyClientScope(Builder $query, string $relationPath = 'rendezVous'): Builder
{
    $user = $this->currentUser();
    if (! $user) {
        return $query->whereRaw('1 = 0');
    }
    return \App\Support\Finance\ClientFinanceDocumentScope::apply($query, $user, $relationPath);
}
```

- [ ] **Step 4: Run → PASS + no Livewire regression**

`php artisan test --filter=ClientFinanceDocumentScopeTest`
Then: `php artisan test --filter=FinanceDocuments` (existing Livewire tests must still pass — the refactor is behavior-preserving).

- [ ] **Step 5: pint + commit**

```bash
vendor/bin/pint app/Support/Finance app/Livewire/Client/FinanceDocumentsClient.php tests/Unit/Finance
git add app/Support/Finance app/Livewire/Client/FinanceDocumentsClient.php tests/Unit/Finance
git commit -m "refactor(finance): extract client finance-document ownership scope (shared, isolation-safe)"
```

---

## Task 5: Invoices list API

**Files:**
- Create: `app/Http/Controllers/Api/Client/InvoiceApiController.php`
- Create: `app/Http/Resources/InvoiceResource.php`
- Modify: `routes/api/client.php`
- Test: `tests/Feature/Api/Client/InvoiceApiTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Api\Client;

use App\Models\FinanceInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/client/invoices')->assertUnauthorized();
    }

    public function test_index_returns_only_the_authenticated_clients_invoices(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $mine = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        $theirs = FinanceInvoice::factory()->create(['client_id' => $other->id]);

        Sanctum::actingAs($me);
        $ids = collect($this->getJson('/api/client/invoices')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id), 'cross-client invoice leak (F4)');
    }

    public function test_index_exposes_effective_status_and_amount(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->getJson('/api/client/invoices')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'number', 'amount', 'currency', 'effective_status', 'due_at']]]);
    }
}
```

- [ ] **Step 2: Run → FAIL (404).** `php artisan test --filter=InvoiceApiTest`

- [ ] **Step 3: Implement**

Create `app/Http/Resources/InvoiceResource.php`:
```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->invoice_number ?? $this->number ?? (string) $this->id,
            'amount' => (float) ($this->total_amount ?? $this->amount ?? 0),
            'currency' => strtoupper((string) ($this->currency ?? 'EUR')),
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus()['status'] ?? $this->status,
            'due_at' => optional($this->due_at)->toIso8601String(),
            'issued_at' => optional($this->issued_at)->toIso8601String(),
        ];
    }
}
```
NOTE: confirm the real column names (`invoice_number`/`total_amount`/`due_at`) against the `finance_invoices` migration + model `$casts`, and `effectiveStatus()` return shape (the model computes `['status'=>...]`). Adjust the resource to the real columns — do not invent.

Create `app/Http/Controllers/Api/Client/InvoiceApiController.php`:
```php
<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\FinanceInvoice;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ClientFinanceDocumentScope::apply(
            FinanceInvoice::query()->with(['payments', 'reminders']),
            $request->user(),
        );

        if (($status = $request->query('status')) && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('invoice_number', 'like', "%{$search}%"));
        }

        $query->orderByDesc($request->query('sort') === 'oldest' ? 'created_at' : 'created_at');
        if ($request->query('sort') === 'oldest') {
            $query->reorder()->orderBy('created_at');
        }

        return InvoiceResource::collection($query->paginate(30));
    }
}
```
NOTE: match the `search` column(s) and `status`/`sort` option values to what `FinanceDocumentsClient::applySearch`/`getStatusOptionsProperty`/`getSortOptionsProperty` use (read them), so the API filters behave identically to the web.

Modify `routes/api/client.php` — inside the `auth:sanctum` group:
```php
Route::get('/client/invoices', [\App\Http\Controllers\Api\Client\InvoiceApiController::class, 'index'])->name('api.client.invoices.index');
```
(Verify it resolves at `/api/client/invoices` — match how the file's other routes resolve under the `api` prefix.)

- [ ] **Step 4: Run → PASS.** `php artisan test --filter=InvoiceApiTest`

- [ ] **Step 5: pint + commit**

```bash
vendor/bin/pint app/Http/Controllers/Api/Client app/Http/Resources routes/api/client.php tests/Feature/Api/Client
git add -A
git commit -m "feat(api): client invoices list endpoint (shared scope, isolation-tested)"
```

---

## Task 6: Invoice detail API

**Files:** Modify `InvoiceApiController.php`, `routes/api/client.php`; extend `InvoiceApiTest`.

- [ ] **Step 1: Add failing tests**

```php
    public function test_show_returns_own_invoice_with_payments_and_reminders(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $invoice = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $this->getJson("/api/client/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id)
            ->assertJsonStructure(['data' => ['id', 'effective_status', 'payments', 'reminders']]);
    }

    public function test_show_forbids_another_clients_invoice(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $theirs = FinanceInvoice::factory()->create(['client_id' => $other->id]);
        Sanctum::actingAs($me);

        $this->getJson("/api/client/invoices/{$theirs->id}")->assertStatus(404); // scoped-out ⇒ not found
    }
```

- [ ] **Step 2: Run → FAIL.** `php artisan test --filter=InvoiceApiTest`

- [ ] **Step 3: Implement `show`** — resolve via the SAME scope (so another client's invoice is simply not in the scoped query → `findOrFail` 404, no leak):

```php
    public function show(Request $request, int $id): InvoiceResource
    {
        $invoice = ClientFinanceDocumentScope::apply(
            FinanceInvoice::query()->with(['payments', 'reminders']),
            $request->user(),
        )->findOrFail($id);

        return new InvoiceResource($invoice);
    }
```
Add to `InvoiceResource::toArray` the nested `payments` + `reminders` (whenLoaded) as minimal arrays (amount/status/date — match real columns). Route:
```php
Route::get('/client/invoices/{id}', [\App\Http\Controllers\Api\Client\InvoiceApiController::class, 'show'])->whereNumber('id')->name('api.client.invoices.show');
```

- [ ] **Step 4: Run → PASS.** `php artisan test --filter=InvoiceApiTest`

- [ ] **Step 5: commit** `git commit -m "feat(api): client invoice detail endpoint (scoped find, isolation-tested)"`

---

## Task 7: Invoice PDF endpoint

**Files:** Modify `InvoiceApiController.php`, `routes/api/client.php`; extend `InvoiceApiTest`.

- [ ] **Step 1: Read the web PDF path.** Read `FinanceDocumentDownloadController@invoice` to see how the invoice PDF is produced (DomPDF view? a service method? a stored file?). The API `download` must produce the SAME PDF via the same path.

- [ ] **Step 2: Add failing test**

```php
    public function test_download_returns_pdf_for_own_invoice(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $invoice = FinanceInvoice::factory()->create(['client_id' => $me->id]);
        Sanctum::actingAs($me);

        $res = $this->get("/api/client/invoices/{$invoice->id}/pdf");
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', strtolower($res->headers->get('content-type') ?? ''));
    }

    public function test_download_forbids_another_clients_invoice_pdf(): void
    {
        $me = User::factory()->create(['role' => 'client']);
        $theirs = FinanceInvoice::factory()->create(['client_id' => User::factory()->create()->id]);
        Sanctum::actingAs($me);
        $this->get("/api/client/invoices/{$theirs->id}/pdf")->assertStatus(404);
    }
```

- [ ] **Step 3: Implement `download`** — resolve via the shared scope (404 if not owned), then return the PDF using the SAME generation path as `FinanceDocumentDownloadController@invoice` (delegate to it or to the shared service method it uses; do NOT reimplement PDF layout). Route:
```php
Route::get('/client/invoices/{id}/pdf', [\App\Http\Controllers\Api\Client\InvoiceApiController::class, 'download'])->whereNumber('id')->name('api.client.invoices.pdf');
```
If the existing controller builds the PDF inline, extract that into a small shared method/service both call (note it). Keep the response `Content-Type: application/pdf`.

- [ ] **Step 4: Run → PASS.** `php artisan test --filter=InvoiceApiTest`

- [ ] **Step 5: commit** `git commit -m "feat(api): client invoice PDF endpoint (scoped, reuses web PDF path)"`

---

## Task 8: Mobile invoices data layer

**Files:**
- Create: `mobile/shared/src/finance/useInvoices.ts`
- Test: `mobile/shared/src/finance/__tests__/useInvoices.test.ts`
- Modify: `mobile/client/jest.config.ts` + `tsconfig.json` (add `@/finance` alias, mirroring how Task-7 of sub-project 1 added `@/webview`/`@/parity`); `mobile/client/babel.config.js` (add `@/finance` module-resolver alias).

- [ ] **Step 1: Add the `@/finance` aliases** in `mobile/client/jest.config.ts` (before the `^@/(.*)$` catch-all), `tsconfig.json` paths, and `babel.config.js` module-resolver — each pointing `@/finance` → `../shared/src/finance`. (This is the same wiring sub-project 1 did for `@/webview`/`@/parity`; if `@/finance` already resolves, skip.)

- [ ] **Step 2: Write the failing test**

```ts
import { fetchInvoices, fetchInvoice, invoicePdfUrl } from '../useInvoices';
import { apiClient } from '@/api';

jest.mock('@/api', () => ({ apiClient: { get: jest.fn() }, ApiError: class extends Error {} }));

describe('invoices data layer', () => {
  beforeEach(() => jest.clearAllMocks());

  it('fetchInvoices passes filters and returns data array', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: [{ id: 1, number: 'F-1', amount: 80, currency: 'EUR', effective_status: 'paid' }] } });
    const result = await fetchInvoices({ status: 'overdue', search: 'F-1' });
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices', { params: { status: 'overdue', search: 'F-1' } });
    expect(result[0].number).toBe('F-1');
  });

  it('fetchInvoice returns a single invoice', async () => {
    (apiClient.get as jest.Mock).mockResolvedValue({ data: { data: { id: 7, number: 'F-7' } } });
    expect((await fetchInvoice(7)).id).toBe(7);
    expect(apiClient.get).toHaveBeenCalledWith('/client/invoices/7');
  });

  it('invoicePdfUrl builds the pdf path', () => {
    expect(invoicePdfUrl(7)).toBe('/client/invoices/7/pdf');
  });
});
```

- [ ] **Step 3: Run → FAIL.** From `mobile/client`: `npx jest useInvoices.test`

- [ ] **Step 4: Implement**

Create `mobile/shared/src/finance/useInvoices.ts`:
```ts
import { apiClient } from '@/api';

export interface Invoice {
  id: number;
  number: string;
  amount: number;
  currency: string;
  status?: string;
  effective_status: string;
  due_at?: string | null;
  issued_at?: string | null;
}

export interface InvoiceFilters { status?: string; search?: string; sort?: string }

export async function fetchInvoices(filters: InvoiceFilters = {}): Promise<Invoice[]> {
  const params: Record<string, string> = {};
  if (filters.status && filters.status !== 'all') params.status = filters.status;
  if (filters.search) params.search = filters.search;
  if (filters.sort) params.sort = filters.sort;
  const res = await apiClient.get('/client/invoices', { params });
  return res.data.data as Invoice[];
}

export async function fetchInvoice(id: number): Promise<Invoice & { payments?: unknown[]; reminders?: unknown[] }> {
  const res = await apiClient.get(`/client/invoices/${id}`);
  return res.data.data;
}

/** Relative API path for the invoice PDF (download handled by the screen via apiClient baseURL). */
export function invoicePdfUrl(id: number): string {
  return `/client/invoices/${id}/pdf`;
}
```
The test asserts `fetchInvoices({status,search})` calls with `params:{status,search}` — keep the param construction matching (omit `sort` when absent).

- [ ] **Step 5: Run → PASS + typecheck.** `npx jest useInvoices.test`; `npm.cmd run typecheck`

- [ ] **Step 6: commit** `git commit -m "feat(mobile): invoices data layer (fetchInvoices/fetchInvoice/invoicePdfUrl)"`

---

## Task 9: Native InvoicesScreen (list + filters)

**Files:**
- Create: `mobile/client/src/screens/InvoicesScreen.tsx`
- Test: `mobile/client/src/screens/__tests__/InvoicesScreen.test.tsx`

- [ ] **Step 1: Write the failing test**

```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { InvoicesScreen } from '../InvoicesScreen';
import * as inv from '@/finance/useInvoices';

jest.mock('@/finance/useInvoices');

const navigation: any = { navigate: jest.fn() };

describe('InvoicesScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (inv.fetchInvoices as jest.Mock).mockResolvedValue([
      { id: 1, number: 'F-001', amount: 80, currency: 'EUR', effective_status: 'paid' },
    ]);
  });

  it('lists the client invoices', async () => {
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
  });

  it('opens an invoice detail on tap', async () => {
    const { getByText } = render(<InvoicesScreen navigation={navigation} />);
    await waitFor(() => getByText('F-001'));
    fireEvent.press(getByText('F-001'));
    expect(navigation.navigate).toHaveBeenCalledWith('InvoiceDetail', { id: 1 });
  });
});
```

- [ ] **Step 2: Run → FAIL.** `npx jest InvoicesScreen.test`

- [ ] **Step 3: Implement** `InvoicesScreen.tsx` — a `Screen` + `FlatList` of invoices from `fetchInvoices`, each row showing number/amount/`effective_status` badge, tapping → `navigation.navigate('InvoiceDetail', { id })`. Add the status filter control (the checklist's status options) calling `fetchInvoices({ status })`. Use `@/ui`, `@/theme`. Match the existing screen patterns (e.g. `BookingsListScreen`). Handle loading/empty/error states (reuse `ErrorState`/`EmptyState` from `@/ui`).

- [ ] **Step 4: Run → PASS + typecheck.** `npx jest InvoicesScreen.test`; `npm.cmd run typecheck`

- [ ] **Step 5: commit** `git commit -m "feat(mobile): native InvoicesScreen list + filters"`

---

## Task 10: Native InvoiceDetailScreen + native PDF share

**Files:**
- Create: `mobile/client/src/screens/InvoiceDetailScreen.tsx`
- Test: `mobile/client/src/screens/__tests__/InvoiceDetailScreen.test.tsx`
- Install: `expo-file-system`, `expo-sharing` (if absent).

- [ ] **Step 1: Write the failing test**

```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { InvoiceDetailScreen } from '../InvoiceDetailScreen';
import * as inv from '@/finance/useInvoices';

jest.mock('@/finance/useInvoices');

const route: any = { params: { id: 7 } };
const navigation: any = { setOptions: jest.fn() };

describe('InvoiceDetailScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (inv.fetchInvoice as jest.Mock).mockResolvedValue({ id: 7, number: 'F-007', amount: 120, currency: 'EUR', effective_status: 'overdue', payments: [], reminders: [] });
    (inv.invoicePdfUrl as jest.Mock).mockReturnValue('/client/invoices/7/pdf');
  });

  it('renders the invoice detail', async () => {
    const { getByText } = render(<InvoiceDetailScreen route={route} navigation={navigation} />);
    await waitFor(() => getByText('F-007'));
  });

  it('has a share/download PDF action', async () => {
    const { getByText } = render(<InvoiceDetailScreen route={route} navigation={navigation} />);
    await waitFor(() => getByText('F-007'));
    // a button labelled to download/share the PDF exists
    fireEvent.press(getByText(/PDF/i));
    expect(inv.invoicePdfUrl).toHaveBeenCalledWith(7);
  });
});
```

- [ ] **Step 2: Run → FAIL.** `npx jest InvoiceDetailScreen.test`

- [ ] **Step 3: Implement** `InvoiceDetailScreen.tsx` — load via `fetchInvoice(route.params.id)`; show number, amount, `effective_status`, due date, payments, reminders. A "Télécharger le PDF" button that downloads the PDF (via `apiClient` baseURL + `invoicePdfUrl(id)` with the auth header — use `expo-file-system` `downloadAsync` with the bearer token, then `expo-sharing` `shareAsync`). Guard `expo-sharing`/`expo-file-system` behind dynamic import + a graceful fallback (per the `expo-go-gotchas` memory: dynamic import to avoid Expo Go crashes). The test only asserts `invoicePdfUrl` is called on press — keep that path reachable.

- [ ] **Step 4: Run → PASS + typecheck.** `npx jest InvoiceDetailScreen.test`; `npm.cmd run typecheck`

- [ ] **Step 5: commit** `git commit -m "feat(mobile): native InvoiceDetailScreen + native PDF share/download"`

---

## Task 11: Wire navigation

**Files:** Modify `navigation/types.ts`, `navigation/RootNavigator.tsx`, `screens/ModuleHubScreen.tsx`.

- [ ] **Step 1: Types** — add to `RootStackParamList`:
```ts
  Invoices: undefined;
  InvoiceDetail: { id: number };
```

- [ ] **Step 2: RootNavigator** — import both screens and register inside the authenticated block:
```tsx
            <Stack.Screen name="Invoices" component={InvoicesScreen} options={{ headerShown: true, title: 'Factures' }} />
            <Stack.Screen name="InvoiceDetail" component={InvoiceDetailScreen} options={{ headerShown: true, title: 'Facture' }} />
```

- [ ] **Step 3: NATIVE_ROUTES** — in `ModuleHubScreen.tsx` add:
```tsx
  invoices: { screen: 'Invoices' },
```

- [ ] **Step 4: typecheck** — `npm.cmd run typecheck` → exit 0.

- [ ] **Step 5: commit** `git commit -m "feat(mobile): wire Invoices/InvoiceDetail into navigation + NATIVE_ROUTES"`

---

## Task 12: Flip the flag + flag-flip & rollback tests + full verification

**Files:** Modify `config/parity.php`; Create `mobile/client/src/screens/__tests__/InvoicesParityFlip.test.tsx`.

- [ ] **Step 1: Flag-flip + rollback test (reuse SP1 pattern)**

```tsx
import React from 'react';
import { render, waitFor, fireEvent } from '@testing-library/react-native';
import { ModuleHubScreen } from '../ModuleHubScreen';
import * as parity from '@/parity';

jest.mock('@/parity');
const navigate = jest.fn();
const navigation: any = { navigate };
const invoices = (mobile: string) => ([{ key: 'invoices', title: 'Factures', icon: 'receipt-outline', path: '/client/finance', mobile }]);

describe('invoices parity flip', () => {
  beforeEach(() => jest.clearAllMocks());

  it('native flag routes to the native Invoices screen', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(invoices('native'));
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Factures'));
    fireEvent.press(getByText('Factures'));
    expect(navigate).toHaveBeenCalledWith('Invoices', undefined);
    expect(navigate).not.toHaveBeenCalledWith('EmbeddedModule', expect.anything());
  });

  it('rollback: webview flag re-routes to EmbeddedModule', async () => {
    (parity.fetchParityMap as jest.Mock).mockResolvedValue(invoices('webview'));
    const { getByText } = render(<ModuleHubScreen navigation={navigation} />);
    await waitFor(() => getByText('Factures'));
    fireEvent.press(getByText('Factures'));
    expect(navigate).toHaveBeenCalledWith('EmbeddedModule', { path: '/client/finance', title: 'Factures' });
  });
});
```

- [ ] **Step 2: Run → confirm the native case passes (NATIVE_ROUTES wired in Task 11), rollback case passes.** `npx jest InvoicesParityFlip.test`

- [ ] **Step 3: Flip the registry flag + fix the stale path** in `config/parity.php` — the invoices entry: `'mobile' => 'native'` and correct `'path' => '/client/finance'` (the real route). One line each.

- [ ] **Step 4: Full verification**

```
php artisan test --filter=InvoiceApiTest
php artisan test --filter=ClientFinanceDocumentScopeTest
php artisan test --filter=FinanceDocuments
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
```
From `mobile/client`: `npm.cmd run typecheck; npx jest useInvoices InvoicesScreen InvoiceDetailScreen InvoicesParityFlip`
All green; Pint/PHPStan clean on new files; 0 skips.

- [ ] **Step 5: Confirm DoD** — rubric doc + backlog (Task 1); playbook (Task 2); invoices migrated full-parity (Tasks 3–11: API list/detail/PDF scoped+isolated, native list+detail+PDF share, nav wired, flag flipped); rollback proven (Step 1–2 here).

- [ ] **Step 6: commit**

```bash
git add config/parity.php mobile/client/src/screens/__tests__/InvoicesParityFlip.test.tsx
git commit -m "feat(migration): flip invoices to native + flag-flip/rollback tests (exemplar complete)"
```

---

## Self-review notes (already applied)

- **Spec coverage:** rubric → Task 1; playbook → Task 2; parity audit → Task 3; the isolation-safe shared scope (spec's core concern) → Task 4; invoices API list/detail/PDF → Tasks 5–7; native screens → Tasks 9–10; data layer → Task 8; nav wiring → Task 11; flag flip + flag-flip & rollback tests → Task 12. Verification (5 items) is embedded per task + Task 12.
- **Isolation discipline:** the ownership scope is extracted ONCE (Task 4) and reused by the API (Tasks 5–7) — directly preventing the duplicate-scope cross-tenant leak class. Every API task has a cross-client isolation assertion.
- **Discovery where needed (not placeholders):** Tasks 3, 4 (full `applyClientScope` body), 5 (real column names), 7 (the web PDF path) say "read the real X first" — this is grounded discovery against named files, and the consuming code (resource/screen shapes) is defined within this plan so it stays internally consistent.
- **Type/name consistency:** `ClientFinanceDocumentScope::apply`, `InvoiceApiController` (`index`/`show`/`download`), `InvoiceResource`, routes `/api/client/invoices[/{id}][/pdf]`, `fetchInvoices`/`fetchInvoice`/`invoicePdfUrl`, `Invoice` type fields (`number`/`amount`/`effective_status`), nav names `Invoices`/`InvoiceDetail`, `NATIVE_ROUTES.invoices`, registry path `/client/finance` are consistent across backend, data layer, screens, and tests.
- **Scope:** invoices only; quotes explicitly out (Task 3 notes it); no scaffold generator; WebView host untouched.
