<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * CL2 — Suppression des tables mortes (jamais référencées par le code).
 *
 * Audit ayant donné 0 références dans app/, tests/, seeders/, factories/ :
 *   - mission_positions      (remplacée par mission_tracking_points)
 *   - knowledge_articles     (Phase 5 LLM utilise un autre système)
 *   - mission_histories      (aucun service ne l'écrit)
 *   - platform_settings      (jamais lue/écrite par le code)
 *   - pricing_logs           (jamais lue/écrite par le code)
 *
 * Approche défensive : Schema::hasTable() avant chaque drop pour ne pas crasher
 * si la migration a déjà tourné ou si la table n'existe pas en local.
 *
 * down() ne recrée PAS les tables (ce serait une régression).
 * Si tu veux récupérer leur structure, voir les migrations originales :
 *   - 2026_05_04_000007_create_mission_tables.php
 *   - 2026_05_04_000010_create_assistant_and_audit_tables.php
 *   - 2026_05_04_000011_create_cleanux_v2_feature_extensions.php
 */
return new class extends Migration {
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
