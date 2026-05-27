<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend trades table with billing_unit and requires_site_visit columns
 * required by the 12-trade multi-métiers spec.
 *
 * Runs before the other 2026-05-27 migrations (timestamp 000000).
 * Defensive: guarded with Schema::hasColumn.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'billing_unit')) {
                $table->string('billing_unit', 30)
                    ->default('hourly')
                    ->after('sort_order')
                    ->comment('hourly | per_m2 | fixed | per_item');
            }

            if (! Schema::hasColumn('trades', 'requires_site_visit')) {
                $table->boolean('requires_site_visit')
                    ->default(false)
                    ->after('billing_unit');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (Schema::hasColumn('trades', 'requires_site_visit')) {
                $table->dropColumn('requires_site_visit');
            }

            if (Schema::hasColumn('trades', 'billing_unit')) {
                $table->dropColumn('billing_unit');
            }
        });
    }
};
