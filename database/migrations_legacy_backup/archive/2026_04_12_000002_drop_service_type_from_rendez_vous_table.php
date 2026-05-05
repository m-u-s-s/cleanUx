<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->dropIndex('rendez_vous_service_type_index');
            $table->dropColumn('service_type');
        });
    }

    public function down(): void
    {
        Schema::table('rendez_vous', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('devis_estime');
            $table->index('service_type');
        });
    }
};
