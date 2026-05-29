<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the 3 empty, unreferenced Tenancy v2 tables. The Tenancy v2 module was
 * removed (2026-05-29, commit 3b165db1) as dead/redundant scaffolding — no model,
 * service, route or foreign key references these tables anymore. They were left in
 * place during the code removal; this migration cleans them up.
 *
 * NOTE: the nullable `tenant_id` columns on `users` and `audit_events` are
 * intentionally KEPT — `AuditService` still writes `audit_events.tenant_id` as a
 * generic (currently-null) tag, and removing it would touch audit logging. Those
 * columns are harmless plain nullable ints (no FK to the dropped tables).
 *
 * One-way cleanup: down() does not recreate the tables (the feature is gone). The
 * original structure remains in 2026_05_19_140001_create_tenancy_v2_tables.php and
 * in git history if it is ever needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }

    public function down(): void
    {
        // Intentionally not restored — Tenancy v2 was permanently removed.
        // See migration 2026_05_19_140001_create_tenancy_v2_tables.php for the
        // original schema if a multi-tenant feature is ever rebuilt.
    }
};
