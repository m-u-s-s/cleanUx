<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\Feedback.
 *
 * The model declares client_user_id and client_organization_id as fillable
 * (modern actor references alongside the legacy client_id) but the feedback
 * table is missing both columns. Added defensively as nullable FK columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback')) {
            return;
        }

        Schema::table('feedback', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback', 'client_user_id')) {
                $table->unsignedBigInteger('client_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('feedback', 'client_organization_id')) {
                $table->unsignedBigInteger('client_organization_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('feedback')) {
            return;
        }

        Schema::table('feedback', function (Blueprint $table) {
            foreach (['client_user_id', 'client_organization_id'] as $col) {
                if (Schema::hasColumn('feedback', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
