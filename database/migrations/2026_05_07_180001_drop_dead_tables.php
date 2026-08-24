<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** CL2 — Suppression des tables mortes (jamais référencées par le code). */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'mission_positions',
            'knowledge_articles',
            'mission_histories',
            'platform_settings',
            'pricing_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::dropIfExists($table);
            }
        }
    }

    public function down(): void
    {
        // Volontairement vide : ces tables sont définitivement supprimées.
        // Si tu veux les recréer, copie le code depuis les migrations originales.
    }
};
