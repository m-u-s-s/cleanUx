<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_action_settings', function (Blueprint $table) {
            // Ce que l'humain a confirme, fige a la bascule. Defaut `false` : une ligne d'avant
            // cette colonne perd l'autonomie des que son action declare toucher au domaine.
            $table->boolean('domaine_au_moment_du_reglage')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('automation_action_settings', function (Blueprint $table) {
            $table->dropColumn('domaine_au_moment_du_reglage');
        });
    }
};
