<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionMedia.
 *
 * The model declares uploaded_by_user_id, media_type, caption, taken_at,
 * lat/lng and meta as fillable/cast with no matching column on mission_media.
 * Added defensively with types matching the casts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_media')) {
            return;
        }

        Schema::table('mission_media', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_media', 'uploaded_by_user_id')) {
                $table->unsignedBigInteger('uploaded_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_media', 'media_type')) {
                $table->string('media_type')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'caption')) {
                $table->string('caption')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'taken_at')) {
                $table->timestamp('taken_at')->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('mission_media', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_media')) {
            return;
        }

        Schema::table('mission_media', function (Blueprint $table) {
            foreach ([
                'uploaded_by_user_id',
                'media_type',
                'caption',
                'taken_at',
                'lat',
                'lng',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('mission_media', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
