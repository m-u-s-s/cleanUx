<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionIncident.
 *
 * The model declares reporter/resolver FKs, incident_type, title,
 * resolution_notes, client_visible, reported_at and meta as fillable/cast
 * with no matching column on mission_incidents. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_incidents')) {
            return;
        }

        Schema::table('mission_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_incidents', 'reported_by_user_id')) {
                $table->unsignedBigInteger('reported_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_incidents', 'resolved_by_user_id')) {
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_incidents', 'incident_type')) {
                $table->string('incident_type')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'client_visible')) {
                $table->boolean('client_visible')->default(false);
            }
            if (! Schema::hasColumn('mission_incidents', 'reported_at')) {
                $table->timestamp('reported_at')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_incidents')) {
            return;
        }

        Schema::table('mission_incidents', function (Blueprint $table) {
            foreach ([
                'reported_by_user_id',
                'resolved_by_user_id',
                'incident_type',
                'title',
                'resolution_notes',
                'client_visible',
                'reported_at',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('mission_incidents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
