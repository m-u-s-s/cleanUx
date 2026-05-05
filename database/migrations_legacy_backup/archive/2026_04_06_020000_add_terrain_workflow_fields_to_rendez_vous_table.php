<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->json('photos_avant')->nullable()->after('photos_reference');
            $table->json('terrain_checklist')->nullable()->after('photos_avant');
            $table->text('remarque_terrain')->nullable()->after('terrain_checklist');
            $table->text('incident_terrain')->nullable()->after('remarque_terrain');
            $table->timestamp('client_presence_confirmed_at')->nullable()->after('incident_terrain');
            $table->string('client_signature_path')->nullable()->after('client_presence_confirmed_at');
            $table->timestamp('mission_arrived_at')->nullable()->after('mission_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropColumn([
                'photos_avant',
                'terrain_checklist',
                'remarque_terrain',
                'incident_terrain',
                'client_presence_confirmed_at',
                'client_signature_path',
                'mission_arrived_at',
            ]);
        });
    }
};
