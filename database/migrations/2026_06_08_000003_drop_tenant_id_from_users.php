<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M7 (column drop) — remove the dead users.tenant_id column + index. It was added for the
 * removed Tenancy v2 module, is read by no scope/logic, and is no longer mass-assignable
 * (removed from User::$fillable in M7 step 1). Distinct from audit_events.tenant_id, which stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if ($this->hasIndex('users', 'users_tenant_id_index')) {
                $table->dropIndex('users_tenant_id_index');
            }
            $table->dropColumn('tenant_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->index('tenant_id');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $index);
        } catch (\Throwable $e) {
            return false;
        }
    }
};
