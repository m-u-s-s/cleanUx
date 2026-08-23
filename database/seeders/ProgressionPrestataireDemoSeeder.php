<?php

namespace Database\Seeders;

use App\Models\AcademyCourse;
use App\Models\ProviderQuest;
use App\Models\SafetyAlert;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** LES OBJECTIFS (E13), L'ACADÉMIE (E16) ET UNE ALERTE OUVERTE (E33), SUR LA BASE DE DÉMONSTRATION. */
class ProgressionPrestataireDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->quetes();
        $this->formations();
        $this->alerteDeDemonstration();

        $this->command?->info('✅ Progression prestataire : objectifs, académie et une veille de sécurité.');
    }

    /** Deux objectifs : un atteignable, un de carrière. LES DEUX FORMES COMPTENT. */
    private function quetes(): void
    {
        $definitions = [
            [
                'code' => 'missions-du-mois',
                'title' => '10 missions ce mois-ci',
                'description' => 'Terminez dix interventions avant la fin du mois.',
                'target' => 10,
                'starts_on' => Carbon::now()->startOfMonth()->toDateString(),
                'ends_on' => Carbon::now()->endOfMonth()->toDateString(),
                'reward_value' => 300,
            ],
            [
                'code' => 'cap-100-missions',
                'title' => 'Cap des 100 missions',
                'description' => 'Un palier de carrière, sans échéance.',
                'target' => 100,
                'starts_on' => null,
                'ends_on' => null,
                'reward_value' => 1500,
            ],
        ];

        foreach ($definitions as $definition) {
            ProviderQuest::query()->updateOrCreate(
                ['code' => $definition['code']],
                array_merge($definition, [
                    'metric' => ProviderQuest::METRIC_MISSIONS,
                    'reward_type' => ProviderQuest::REWARD_LOYALTY,
                    'is_active' => true,
                ]),
            );
        }
    }

    /** Deux formations, dont une qui débloque un badge. */
    private function formations(): void
    {
        $metier = Trade::query()->orderBy('id')->first();

        $definitions = [
            [
                'code' => 'produits-et-surfaces',
                'title' => 'Produits et surfaces : ne pas abîmer',
                'summary' => 'Quinze minutes pour éviter les trois erreurs les plus coûteuses.',
                'duration_minutes' => 15,
                'badge_code' => 'quality_pro',
                'specialty_bonus' => 5,
                'trade_id' => $metier?->id,
            ],
            [
                'code' => 'arriver-et-repartir',
                'title' => 'Arriver et repartir : la première et la dernière minute',
                'summary' => 'Ce que le client retient d’une intervention se joue en deux minutes.',
                'duration_minutes' => 10,
                'badge_code' => null,
                'specialty_bonus' => 3,
                'trade_id' => null,
            ],
        ];

        foreach ($definitions as $definition) {
            AcademyCourse::query()->updateOrCreate(
                ['code' => $definition['code']],
                array_merge($definition, ['is_published' => true]),
            );
        }
    }

    /** Une VEILLE ouverte, pour que le centre de sécurité ait quelque chose à montrer. */
    private function alerteDeDemonstration(): void
    {
        if (SafetyAlert::query()->exists()) {
            return;
        }

        $prestataire = User::query()
            ->whereHas('providerProfile')
            ->orderBy('id')
            ->first();

        if (! $prestataire) {
            return;
        }

        SafetyAlert::query()->create([
            'user_id' => $prestataire->id,
            'level' => SafetyAlert::LEVEL_CHECK_IN,
            'status' => SafetyAlert::STATUS_OPEN,
            'message' => 'Le client est très insistant, je préfère que quelqu’un garde un œil.',
            'lat' => 50.8466,
            'lng' => 4.3528,
        ]);
    }
}
