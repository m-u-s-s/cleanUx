<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->decimal('destination_lat', 10, 7)->nullable()->after('end_lng');
            $table->decimal('destination_lng', 10, 7)->nullable()->after('destination_lat');

            $table->index(['destination_lat', 'destination_lng']);
        });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropIndex(['destination_lat', 'destination_lng']);
            $table->dropColumn(['destination_lat', 'destination_lng']);
        });
    }
};