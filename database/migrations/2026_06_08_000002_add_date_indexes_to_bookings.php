<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M20 — the legacy bookings.date column is filtered/sorted heavily (client & employe
 * dashboards, matching workload, subscription scheduler) but had no index, forcing
 * filesort + row scans. Add composite indexes covering those access paths.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'date')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            if (! $this->hasIndex('bookings', 'bookings_employe_id_date_index')) {
                $table->index(['employe_id', 'date'], 'bookings_employe_id_date_index');
            }
            if (! $this->hasIndex('bookings', 'bookings_client_id_date_index')) {
                $table->index(['client_id', 'date'], 'bookings_client_id_date_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['bookings_employe_id_date_index', 'bookings_client_id_date_index'] as $name) {
                if ($this->hasIndex('bookings', $name)) {
                    $table->dropIndex($name);
                }
            }
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
