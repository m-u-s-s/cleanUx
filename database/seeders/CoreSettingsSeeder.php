<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

class CoreSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'duree_creneau' => '30',
            'booking_min_notice_hours_default' => '24',
            'zones_feature_enabled' => '1',
            'calendar_sync_enabled' => '0',
            'entreprise_module_enabled' => '1',
        ];

        foreach ($settings as $cle => $valeur) {
            Parametre::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
        }

        $this->command?->info('✅ Paramètres cœur plateforme initialisés.');
    }
}
