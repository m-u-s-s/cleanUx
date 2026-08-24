<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Drops the 3 empty, unreferenced Tenancy v2 tables. */
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
