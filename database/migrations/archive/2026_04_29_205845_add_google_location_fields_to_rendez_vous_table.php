<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            if (! Schema::hasColumn('rendez_vous', 'google_place_id')) {
                $table->string('google_place_id')->nullable()->after('adresse');
            }

            if (! Schema::hasColumn('rendez_vous', 'destination_lat')) {
                $table->decimal('destination_lat', 10, 7)->nullable()->after('google_place_id');
            }

            if (! Schema::hasColumn('rendez_vous', 'destination_lng')) {
                $table->decimal('destination_lng', 10, 7)->nullable()->after('destination_lat');
            }

            if (! Schema::hasColumn('rendez_vous', 'address_components')) {
                $table->json('address_components')->nullable()->after('destination_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            if (Schema::hasColumn('rendez_vous', 'address_components')) {
                $table->dropColumn('address_components');
            }

            if (Schema::hasColumn('rendez_vous', 'google_place_id')) {
                $table->dropColumn('google_place_id');
            }
        });
    }
};