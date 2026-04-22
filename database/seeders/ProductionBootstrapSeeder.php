<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferencePlatformSeeder::class,
        ]);

        $this->command?->info('✅ Bootstrap production chargé : uniquement les données de référence et la configuration plateforme.');
    }
}
