<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'access_scope')) {
                $table->string('access_scope')->default('all')->after('role');
            }

            if (! Schema::hasColumn('users', 'managed_service_zone_id')) {
                $table->foreignId('managed_service_zone_id')
                    ->nullable()
                    ->after('primary_service_zone_id')
                    ->constrained('service_zones')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'permissions')) {
                $table->json('permissions')->nullable()->after('managed_service_zone_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'managed_service_zone_id')) {
                $table->dropConstrainedForeignId('managed_service_zone_id');
            }

            if (Schema::hasColumn('users', 'permissions')) {
                $table->dropColumn('permissions');
            }

            if (Schema::hasColumn('users', 'access_scope')) {
                $table->dropColumn('access_scope');
            }
        });
    }
};
