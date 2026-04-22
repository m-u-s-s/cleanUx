<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReferencePlatformSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BelgiumGeographySeeder::class,
            ServiceCatalogSeeder::class,
            PlatformModuleSeeder::class,
            ZoneManagementSeeder::class,
            CoreSettingsSeeder::class,
        ]);

        $this->command?->info('✅ Référentiel plateforme chargé (géographie, services, modules, zones, paramètres).');
    }
}
