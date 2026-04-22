<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class CheckMissingTables extends Command
{
    protected $signature = 'db:check-missing-tables';
    protected $description = 'Vérifie les tables manquantes ou mal reliées dans la base de données';

    public function handle()
    {
        $this->info('🔍 Vérification des tables manquantes…');

        // Récupère toutes les tables existantes
        $existingTables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();

        // Tables attendues (ajoutez ici celles utilisées dans vos relations ou migrations)
        $requiredTables = [
            'users',
            'rendez_vous',
            'disponibilites',
            'feedback',
            'limites_journalieres',
        ];

        $missing = [];

        foreach ($requiredTables as $table) {
            if (!in_array($table, $existingTables)) {
                $missing[] = $table;
            }
        }

        if (empty($missing)) {
            $this->info('✅ Toutes les tables attendues sont présentes.');
        } else {
            $this->error('❌ Tables manquantes :');
            foreach ($missing as $table) {
                $this->line('- ' . $table);
            }
        }

        return 0;
    }
}
