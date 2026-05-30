# Parity checklist — invoices

Web surface: `app/Livewire/Client/FinanceDocumentsClient.php`
(route `client.finance` → `GET /dashboard/client/finance`)

Registry path reconciliation: `config/parity.php` `invoices.path` is `'/client/invoices'` (stale) — real registered route is `client.finance` resolving to `/dashboard/client/finance`. Update the `path` key on flag flip.

Scope decision: INVOICES only. Quotes (`FinanceQuote`) are explicitly OUT of scope for this exemplar (a separate future `quotes` module). The web component renders both quotes and invoices on the same page (`documentType` toggle: `all` / `quotes` / `invoices`); the native screen covers the `invoices` half only. The finance summary KPIs and payment health widget reference both types in their counts but are included here because they are the primary at-a-glance indicators a client checks when opening the invoices screen — omitting them would break meaningful parity.

---

## Actions / views (full parity required — INVOICES half only)

- [ ] **List invoices** with:
  - Status filter options (exact values): `all` ("Tous"), `draft` ("Brouillon"), `sent` ("Envoyé"), `accepted` ("Accepté"), `issued` ("Émise"), `partial` ("Partiel"), `paid` ("Payée"), `overdue` ("En retard")
  - Sort options (exact values): `recent` ("Plus récent"), `oldest` ("Plus ancien"), `amount_desc` ("Montant décroissant"), `amount_asc` ("Montant croissant")
  - Search matches on: `invoice_number` (LIKE), `status` (LIKE), booking `ville`, booking `adresse`, booking `booking_reference`, booking `serviceCatalog.name`
  - Results capped at 10 per load (no pagination on web; native may add pull-to-load-more as an upgrade)
  - Active-filter label display: composite string `"<docType> · <status label> · Recherche : <term>"` — show on native as a filter summary chip
  - Reset-filters action clears `status`, `search`, `sort` back to defaults (`all`, `''`, `recent`)

- [ ] **Finance summary KPIs** (invoice-relevant subset of `getFinanceSummaryProperty`):
  - `invoices_count` — total invoice count for the client
  - `paid_count` — invoices with `status = 'paid'`
  - `partial_count` — invoices with `status = 'partial'`
  - `overdue_count` — invoices with `status = 'overdue'`
  - `outstanding_total` — sum of `balance_due` across all invoices (rounded to 2 dp), formatted with `currency_symbol`
  - `next_due_at` — due date of the earliest open invoice with `balance_due > 0` (nullable; display "Prochaine échéance : dd/mm/yyyy" when present)
  - `currency_symbol` — derived from the first invoice's `currency` column (EUR → `€`, USD → `$`, GBP → `£`, CHF → `CHF`); falls back to `€`
  - (Quote counts `quotes_count`, `quotes_pending`, `quotes_accepted` are present in the same property but are OUT of scope for the invoices screen — do not expose them)

- [ ] **Payment health widget** (computed from `getPaymentHealthProperty`, driven by `overdue_count` and `outstanding_total`):
  - `tone` / `label` / `title` / `message` with three states:
    - **rose** — "Action requise" / "Facture(s) en retard" / "Une ou plusieurs factures nécessitent votre attention." (when `overdue_count > 0`)
    - **amber** — "À surveiller" / "Solde ouvert" / "Vous avez encore un montant à régler." (when `outstanding_total > 0` and no overdue)
    - **emerald** — "À jour" / "Situation saine" / "Aucun retard de paiement détecté." (all clear)
  - Also shows `outstanding_total` + `next_due_at` inline in the same hero widget

- [ ] **Per-invoice row fields** (from `invoice-card.blade.php` and `FinanceInvoice` model):
  - `invoice_number` — bold document identifier
  - `status` — raw string badge; invoice-specific statuses in use: `issued`, `partial`, `paid`, `overdue` (plus `draft`/`sent`/`accepted` possible at DB level); badge colour map: `paid` → emerald, `partial` → amber, `overdue` → rose, `issued` → sky, anything else → slate
  - `rendezVous.service_display_name` — service label (fallback: "Service non précisé")
  - `issued_at` — formatted `dd/mm/yyyy`; displayed as "Émise le …"
  - `due_at` — formatted `dd/mm/yyyy`; displayed as "Échéance …" (dash if null)
  - `balance_due` — formatted money string using `formatDocumentMoney()` (respects `currency_symbol`, `currency_position`, decimal/thousands separators from `snapshot`/`meta`); shown with amber colour if `> 0`, emerald if `= 0`
  - `total_amount` — formatted money string (same formatter), shown prominently as the headline amount
  - Overdue warning label visible when `status === 'overdue'`

- [ ] **Invoice detail** (data already eager-loaded by `baseInvoicesQuery`):
  - `payments` relation (`FinancePayment`): per-payment `payment_reference`, `amount`, `status`, `paid_at` / `created_at`
  - `reminders` relation (`FinanceReminder`): list of reminder records (fields TBD when detail screen is designed)
  - Effective / refreshed status via `FinanceInvoice::refreshPaymentStatus()` logic: `paid` when `balance_due ≤ 0 && total > 0`; `partial` when `paid > 0 && balance > 0`; `overdue` when `due_at` past and current status in `['issued', 'sent', 'partial']`

- [ ] **Latest payment events panel** (from `getLatestPaymentEventsProperty`):
  - Up to 5 most-recent payments across all the client's invoices (last 15 invoices, flattened)
  - Per-event: `payment_reference` (fallback "Paiement"), `paid_at` or `created_at` formatted `dd/mm/yyyy HH:mm`, `amount` formatted, `status`

- [ ] **Download invoice PDF**
  - Web route: `client.finance.invoice.download` → `GET /dashboard/client/finance/factures/{invoice}/telecharger` → `FinanceDocumentDownloadController@invoice`
  - Native: trigger download / share (see Device upgrades below)

---

## API gaps (no client invoices API exists today — all are gaps)

- [ ] `GET /api/client/invoices` — list, with query params: `status`, `search`, `sort` (`recent`|`oldest`|`amount_desc`|`amount_asc`); returns paginated invoices with per-row fields above + `finance_summary` block
- [ ] `GET /api/client/invoices/{id}` — detail: all row fields + `payments[]` + `reminders[]` + computed `payment_health`
- [ ] `GET /api/client/invoices/{id}/pdf` — returns the PDF binary (reusing `FinanceDocumentDownloadController@invoice` behind an API-auth guard) or a short-lived signed URL

---

## Device upgrades (per rubric)

- Native PDF share / download via `expo-sharing` + `expo-file-system` instead of opening in an in-WebView viewer — user gets the native share sheet (AirDrop, save to Files, email attachment, etc.)
- Pull-to-refresh and infinite scroll (load-more) replacing the web's hard cap of 10 results

---

## Rollback

Flip `config/parity.php` key `invoices` → `'mobile' => 'webview'` and also correct `'path' => '/dashboard/client/finance'`.

---

## Intertwining note

The web component intentionally blends quotes and invoices under one URL with a `documentType` tab switcher. The `getFinanceSummaryProperty` returns mixed counts (both `quotes_count`/`quotes_pending`/`quotes_accepted` and invoice counters). The invoice-only native screen should expose invoice-relevant KPIs only (`invoices_count`, `paid_count`, `partial_count`, `overdue_count`, `outstanding_total`, `next_due_at`) and omit quote KPIs. The `getPaymentHealthProperty` is pure-invoice (it reads `overdue_count` and `outstanding_total` from invoice rows only) and should be replicated in full.
