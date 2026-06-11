<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bundle Marketplace — zone du chantier, pour ne solliciter que les prestataires
 * couvrant la zone (en plus du métier + KYC).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('multi_trade_bundles', function (Blueprint $table) {
            $table->foreignId('service_zone_id')->nullable()->after('client_user_id')
                ->constrained('service_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('multi_trade_bundles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_zone_id');
        });
    }
};
