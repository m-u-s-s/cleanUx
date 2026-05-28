<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\Booking.
 *
 * The model declares these fillable/cast attributes (real mission & lifecycle
 * fields written by the dispatch/approval/terrain flows) but the bookings
 * table is missing the columns. Added defensively with types matching $casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            // FK references (->index)
            if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                $table->unsignedBigInteger('assigned_provider_organization_id')->nullable()->index();
            }
            if (! Schema::hasColumn('bookings', 'provider_team_id')) {
                $table->unsignedBigInteger('provider_team_id')->nullable()->index();
            }
            if (! Schema::hasColumn('bookings', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->index();
            }

            // Timestamps (datetime casts)
            if (! Schema::hasColumn('bookings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'mission_arrived_at')) {
                $table->timestamp('mission_arrived_at')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'client_presence_confirmed_at')) {
                $table->timestamp('client_presence_confirmed_at')->nullable();
            }

            // JSON / array casts
            if (! Schema::hasColumn('bookings', 'options')) {
                $table->json('options')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'areas')) {
                $table->json('areas')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'photos_avant')) {
                $table->json('photos_avant')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'photos_apres')) {
                $table->json('photos_apres')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'terrain_checklist')) {
                $table->json('terrain_checklist')->nullable();
            }

            // String
            if (! Schema::hasColumn('bookings', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            foreach ([
                'assigned_provider_organization_id', 'provider_team_id', 'approved_by',
                'approved_at', 'mission_arrived_at', 'client_presence_confirmed_at',
                'options', 'areas', 'photos_avant', 'photos_apres', 'terrain_checklist',
                'contact_email',
            ] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
