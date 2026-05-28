<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\LimiteJournaliere.
 *
 * The model declares verrou_admin (cast boolean — an admin lock flag) as
 * fillable but the limites_journalieres table has no such column. Added
 * defensively as a boolean defaulting to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('limites_journalieres')) {
            return;
        }

        Schema::table('limites_journalieres', function (Blueprint $table) {
            if (! Schema::hasColumn('limites_journalieres', 'verrou_admin')) {
                $table->boolean('verrou_admin')->default(false);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('limites_journalieres')) {
            return;
        }

        Schema::table('limites_journalieres', function (Blueprint $table) {
            if (Schema::hasColumn('limites_journalieres', 'verrou_admin')) {
                $table->dropColumn('verrou_admin');
            }
        });
    }
};
