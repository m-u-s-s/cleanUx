<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provider_favorites')) {
            Schema::table('provider_favorites', function (Blueprint $table) {
                if (! Schema::hasColumn('provider_favorites', 'is_favorite')) {
                    $table->boolean('is_favorite')->default(true);
                }
            });
        }

        if (Schema::hasTable('mission_assignments')) {
            Schema::table('mission_assignments', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_assignments', 'role_on_mission')) {
                    $table->string('role_on_mission')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        //
    }
};
