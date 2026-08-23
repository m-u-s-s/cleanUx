<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\MissionExtra;
use App\Models\User;
use Illuminate\Database\Seeder;

/** LES SUPPLÉMENTS PROPOSÉS SUR PLACE (F3, F12), SUR LA BASE DE DÉMONSTRATION. */
class ExtrasDeMissionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $mission = Mission::query()
            ->whereNotNull('lead_provider_user_id')
            ->orderByDesc('id')
            ->first();

        if (! $mission) {
            $this->command?->warn('⚠️ Aucune mission assignée : extras ignorés.');

            return;
        }

        $prestataire = User::query()->find($mission->lead_provider_user_id);

        if (! $prestataire) {
            return;
        }

        $definitions = [
            [
                'label' => 'Nettoyage des vitres extérieures',
                'description' => 'Non prévu à la commande, proposé sur place.',
                'price_cents' => 3500,
                // En attente : c'est le client qui tranche, et l'écran doit pouvoir le montrer.
                'status' => MissionExtra::STATUS_PROPOSED,
            ],
            [
                'label' => 'Détartrage de la douche',
                'description' => 'Entartrage important constaté à l’arrivée.',
                'price_cents' => 2500,
                // APPROUVÉ MAIS PAS FACTURÉ, et c'est le cas qui compte.
                'status' => MissionExtra::STATUS_APPROVED,
                'approved_at' => now()->subHour(),
            ],
        ];

        foreach ($definitions as $definition) {
            MissionExtra::query()->updateOrCreate(
                ['mission_id' => $mission->id, 'label' => $definition['label']],
                array_merge($definition, [
                    'proposed_by_user_id' => $prestataire->id,
                    'currency' => 'EUR',
                ]),
            );
        }

        $this->command?->info('✅ Extras de mission : un proposé, un approuvé non facturé.');
    }
}
