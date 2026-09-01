<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            // L'identité d'un seeder, jamais celle du produit : `declencheur` reste libre pour
            // qu'un administrateur pose sa propre règle sur le même événement. NULL pour toute
            // règle créée à la main — un index unique laisse passer plusieurs NULL sur les deux
            // moteurs (mesuré MySQL et SQLite), donc aucune collision entre elles.
            $table->string('cle_de_reference')->nullable()->unique()->after('declencheur');
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropUnique(['cle_de_reference']);
            $table->dropColumn('cle_de_reference');
        });
    }
};
