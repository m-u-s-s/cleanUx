<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\IncidentReport.
 *
 * The model declares assigned_to (FK), photos (cast array), resolution_notes
 * and the first_response_at/resolved_at/closed_at lifecycle timestamps as
 * fillable but the incident_reports table is missing the columns. Added
 * defensively with matching types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('incident_reports')) {
            return;
        }

        Schema::table('incident_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('incident_reports', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
            }
            if (! Schema::hasColumn('incident_reports', 'photos')) {
                $table->json('photos')->nullable();
            }
            if (! Schema::hasColumn('incident_reports', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable();
            }
            if (! Schema::hasColumn('incident_reports', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable();
            }
            if (! Schema::hasColumn('incident_reports', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable();
            }
            if (! Schema::hasColumn('incident_reports', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('incident_reports')) {
            return;
        }

        Schema::table('incident_reports', function (Blueprint $table) {
            foreach ([
                'assigned_to', 'photos', 'resolution_notes',
                'first_response_at', 'resolved_at', 'closed_at',
            ] as $col) {
                if (Schema::hasColumn('incident_reports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
