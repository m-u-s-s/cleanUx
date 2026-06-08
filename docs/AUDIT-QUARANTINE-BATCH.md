# Audit remediation — Quarantine batch (needs sign-off)

These 12 findings from `AUDIT-PLATEFORME-2026-06-08` were **deliberately not auto-fixed**
during the `fix/audit-49-remediation` branch because each is either destructive
(drop/rename/delete) or high-blast-radius, and the constraint was *"ne supprimer aucune
fonctionnalité"*. Each entry below gives the **safe, non-destructive path** so you can approve
them one at a time.

Legend — **Effort**: S (<1h), M (half-day), L (1–2 days). **Risk** is the risk of the *safe path*, not the raw fix.

---

## M3 — Mission/dispute photos on a public disk (RGPD leak)

- **Problem:** `config/quality.php` `photo_storage_disk` defaults to `public`; quality/dispute
  photos (personal data) are served without auth. Inconsistent with `MissionFieldActionController` (private).
- **Why quarantined:** flipping the disk to `private` makes **already-stored** photos unreachable
  and breaks every view that renders them via `Storage::url()`.
- **Safe path:**
  1. Add a dedicated `private` disk (e.g. `quality_private`) and point `QUALITY_PHOTO_DISK` at it
     for **new** uploads only.
  2. Serve photos through a signed, authorized route (`Storage::disk(...)->temporaryUrl()` or a
     controller that checks the C1 ownership policy) instead of `Storage::url()`. Update the
     Blade/JSON serializers that currently emit public URLs.
  3. Write a one-off command to move existing `public` photos to the private disk and rewrite
     stored paths (backfill), run it, then remove public write access.
- **Risk:** Medium (display surface + data move). **Effort:** M–L.
- **Test plan:** unauthorized GET of a photo URL → 403; owner → 200; backfill command idempotent.

---

## M4 — Double commission deduction in the wallet ledger

- **Problem:** `ProviderWalletService::recordEarning()` credits the **net** `provider_amount_cents`
  *and then* debits `platform_fee_cents`, so the wallet balance = net − fee = total − 2×commission.
  Verified: the `TYPE_PLATFORM_FEE` debit is created only here; no other code reads those debit rows.
- **Why quarantined:** removing the debit changes the meaning of **every historical** wallet row and
  the balance of every provider; it's a money-ledger reshape, not a localized fix.
- **Safe path (pick one convention, then migrate):**
  - **Option A (recommended):** stop writing the fee debit; credit net only. Add a reversing
    `adjustment_credit` (= sum of past `platform_fee` debits per provider) in a one-off, audited
    migration so existing balances correct themselves without deleting history.
  - **Option B:** switch to gross-credit + fee-debit (credit `payment_amount_cents`, keep the debit) —
    also net-correct, larger write change.
  - Update `ProviderWalletServiceTest` (currently asserts the buggy `70.00`).
- **Risk:** High (touches real balances). **Effort:** M. **Needs:** explicit accounting decision.

---

## M6 — `customer_credits` schema double-defined (wallet vs credit-per-booking)

- **Problem:** table created in "wallet" shape (`balance`, `currency`, + ledger) then bolted with a
  "credit-per-booking" shape (`client_id`, `rendez_vous_id`, `amount`, `remaining_amount`, `status`);
  the model only knows the second. Memory already says *"ne pas écrire dans CustomerCredit::create"*.
- **Why quarantined:** dropping either column set is destructive and the live data shape is ambiguous.
- **Safe path:**
  1. Decide the canonical model (wallet **or** credit-per-booking).
  2. Backfill the chosen columns from the dead ones (data migration), keeping the dead columns
     **temporarily** (deprecated, nullable).
  3. Point the `CustomerCredit` model + all writers at the canonical columns; add tests.
  4. **Separately**, after a soak period, drop the dead columns (a later, second sign-off).
- **Risk:** High (money/credits). **Effort:** L. **Needs:** product decision on the model.

---

## M7 — Dead `users.tenant_id` column + index

- **Problem:** `users.tenant_id` was added for the removed Tenancy v2 module; no scope/logic uses it.
  Note: `audit_events.tenant_id` (used by `AuditService`) is a **different** column — keep that one.
- **Why quarantined:** dropping a column is irreversible; want to confirm zero live reads first.
- **Safe path:**
  1. First (non-destructive, can do now if approved): remove `'tenant_id'` from `User::$fillable`
     so it's no longer mass-assignable, and remove the dead migration comment referencing the
     non-existent `BelongsToTenant` trait.
  2. Later: drop the `users.tenant_id` column + index via a dedicated down migration.
- **Risk:** Low. **Effort:** S. (Step 1 alone closes the mass-assignment surface with zero risk.)

---

## M8 — `bookings.surface` (varchar tranche) vs `surface_m2` (int)

- **Problem:** two near-identical columns of incompatible types/semantics (range string vs numeric m²).
  Verified: **no app/resource code reads `surface`** today (only the booking form writes it).
- **Why quarantined:** a rename touches the writer and any DB/report consumer; needs confirmation.
- **Safe path:**
  1. Add a new explicit column `surface_range` (string), backfill from `surface`.
  2. Update the booking form/FormRequest to write `surface_range`; keep `surface` written in parallel
     during a transition.
  3. Later: drop `surface` once nothing writes it.
- **Risk:** Low–Medium. **Effort:** M.

---

## M19 — OnboardingV2 bypassed by the real provider onboarding

- **Problem:** `OnboardingV2\OnboardingEngine` (+ API + tests) exists but the actual provider wizard
  and `ProviderOnboardingController` use the legacy `ProviderOnboardingService`; two unsynced
  progression registries.
- **Why quarantined:** "migrate the wizard to V2" is a large feature change; "remove V2" deletes a
  whole module — both need a product call.
- **Safe path (incremental, recommended = migrate):**
  1. Make the legacy wizard **also** advance the V2 journey (write-through adapter) so state converges
     without a big-bang switch.
  2. Move one step at a time to the engine behind a feature flag; verify parity.
  3. Once the wizard fully runs on V2, deprecate `ProviderOnboardingService`.
  - Alternatively, if V2 is not the intended path, remove it in its own PR (separate sign-off).
- **Risk:** Medium–High. **Effort:** L. **Needs:** decision — migrate vs remove.

---

## M25 — Tests run with foreign keys disabled + silent schema skips

- **Problem:** `phpunit.xml` sets `DB_FOREIGN_KEYS=false`; several tests `markTestSkipped` when a
  table/column is missing → migration regressions pass silently (9 skips remain suite-wide).
- **Why quarantined:** flipping `DB_FOREIGN_KEYS=true` globally is high-blast-radius — many existing
  tests create orphan fixtures and would fail at once.
- **Safe path:**
  1. Add a **separate CI job** that runs the money/GDPR/Spine suites against MySQL/Postgres with FKs
     on (don't flip the default SQLite suite).
  2. Replace `markTestSkipped` on missing schema with a hard failure in the money/assistant namespaces
     (we already removed two such skips in H1 and M22).
  3. Incrementally fix orphan-creating fixtures, then flip the default once the suite is clean.
- **Risk:** Medium (lots of small test fixes). **Effort:** L.

---

## F1-removal — Delete legacy `cancel-with-fee` route + `CancelBookingService`

- **Status:** the **non-destructive** half is **already shipped** (F1): the legacy client API now
  delegates to the V2 engine, so fees no longer diverge by channel.
- **Why quarantined:** physically deleting the route + `CancelBookingService` could break any external
  caller still hitting `/cancel-with-fee` and removes a still-referenced service (Phase14 tests).
- **Safe path:**
  1. Add deprecation logging on the legacy route; confirm via logs that no client still calls it.
  2. Migrate the Phase14 tests that call `CancelBookingService` directly onto the V2 engine.
  3. Then delete the route + service in a dedicated PR.
- **Risk:** Low (behavior already converged). **Effort:** S–M.

---

## L4 — Type-FK columns added without FK constraints

- **Problem:** several `*_id` columns (mission_id, channel_id, country_id, `customer_credits.rendez_vous_id`,
  …) are plain `unsignedBigInteger` with no `constrained()`; no referential integrity.
- **Why quarantined:** adding FK constraints **fails if orphan rows already exist** in prod, and FK
  enforcement can change delete behavior.
- **Safe path:**
  1. Audit each column for orphan rows (`WHERE x_id NOT IN (SELECT id FROM …)`); clean/nullify them.
  2. Add FKs with explicit `nullOnDelete()`/`cascadeOnDelete()` per relation, one migration, after the
     cleanup.
  3. Verify on a prod-data copy first.
- **Risk:** Medium (prod data dependent). **Effort:** M.

---

## L7 — KYC/KYB sensitive data stored unencrypted

- **Problem:** `kyc_verifications.metadata/result_summary`, `beneficial_owners.date_of_birth` stored as
  plaintext; no `encrypted` cast (art.32).
- **Why quarantined:** adding an `encrypted` cast to a column that **already holds plaintext** makes
  existing rows undecryptable (the cast tries to decrypt raw text and throws).
- **Safe path:**
  1. Add the `encrypted`/`encrypted:array` cast for **new writes**, guarded by a one-off migration
     that first encrypts existing plaintext values in place (read raw → `Crypt::encrypt` → write).
  2. Run the backfill, then enable the cast.
  3. Add a test round-tripping a sensitive field.
- **Risk:** High (data corruption if cast enabled before backfill). **Effort:** M. **Order is critical.**

---

## L9 — Dead private methods in `CancellationFeeCalculator`

- **Problem:** `bookingAmount()` (l.217) and `scheduledAt()` (l.227) are unreferenced. Verified: zero
  call sites.
- **Why quarantined:** grouped with the destructive batch only because it's a deletion; genuinely safe.
- **Safe path:** delete both methods. (Lowest-risk item here — safe to approve immediately.)
- **Risk:** Negligible. **Effort:** S.

---

## Suggested order of approval

1. **Now / trivial:** L9 (delete dead code), M7 step 1 (drop fillable + dead comment), F1-removal steps 1–2.
2. **Data-backfill (one decision each):** M4, L7, M8, L4.
3. **Architecture decisions:** M6 (data model), M19 (migrate vs remove), M3 (private media), M25 (CI FK job).

Each can be a small dedicated PR with its own tests; none should ride along with another.
