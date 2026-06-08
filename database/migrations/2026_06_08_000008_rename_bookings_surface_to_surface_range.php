<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M8 — disambiguate the two surface columns: bookings.surface holds a tranche identifier string
 * ("50_100", "plus_250") while surface_m2 is the numeric measurement. Rename the string column to
 * surface_range so the names reflect their semantics. The Booking model keeps a backward-compatible
 * `surface` virtual attribute (accessor + mutator) bridging to surface_range, so all existing
 * code/views/forms using ->surface keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookings', 'surface') && ! Schema::hasColumn('bookings', 'surface_range')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->renameColumn('surface', 'surface_range');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'surface_range') && ! Schema::hasColumn('bookings', 'surface')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->renameColumn('surface_range', 'surface');
            });
        }
    }
};
