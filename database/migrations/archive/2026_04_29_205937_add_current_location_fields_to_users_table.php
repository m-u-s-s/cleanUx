<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'current_lat')) {
                $table->decimal('current_lat', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('users', 'current_lng')) {
                $table->decimal('current_lng', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('users', 'last_location_at')) {
                $table->timestamp('last_location_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'current_lat',
                'current_lng',
                'last_location_at',
            ]);
        });
    }
};
