<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\MissionQualityReview.
 *
 * The model declares reviewer_user_id, reviewer_role, final_status, the three
 * sub-scores (cleanliness/punctuality/behavior, integer 1-5 per the factory),
 * comment and meta as fillable/cast with no matching column on
 * mission_quality_reviews. Added defensively.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_quality_reviews')) {
            return;
        }

        Schema::table('mission_quality_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_quality_reviews', 'reviewer_user_id')) {
                $table->unsignedBigInteger('reviewer_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'reviewer_role')) {
                $table->string('reviewer_role')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'final_status')) {
                $table->string('final_status')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'cleanliness_score')) {
                $table->unsignedInteger('cleanliness_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'punctuality_score')) {
                $table->unsignedInteger('punctuality_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'behavior_score')) {
                $table->unsignedInteger('behavior_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'comment')) {
                $table->text('comment')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_quality_reviews')) {
            return;
        }

        Schema::table('mission_quality_reviews', function (Blueprint $table) {
            foreach ([
                'reviewer_user_id',
                'reviewer_role',
                'final_status',
                'cleanliness_score',
                'punctuality_score',
                'behavior_score',
                'comment',
                'meta',
            ] as $col) {
                if (Schema::hasColumn('mission_quality_reviews', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
