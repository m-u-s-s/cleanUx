<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\MissionExtra;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LES SUPPLÉMENTS PROPOSÉS SUR PLACE (F3, F12), SUR LA BASE DE DÉMONSTRATION.
 *
 * SANS DONNÉES, LE MODULE NE PROUVE RIEN. Un écran d'extras vide ne distingue pas « ce prestataire
 * n'a rien proposé » de « la requête est fausse » — et le premier cas est l'état nominal de presque
 * toutes les missions, ce qui rend la confusion certaine.
 *
 * DEUX LIGNES, ET LEUR ÉCART EST TOUT L'INTÉRÊT. Une proposée, qui attend le client ; une approuvée
 * mais NON FACTURÉE. `approved` n'est pas `charged`, et c'est la distinction qui protège du double
 * prélèvement : sans deux exemples, personne ne voit que ce sont deux états.
 *
 * IDEMPOTENT : la recherche porte sur (mission, libellé), stable d'une exécution à l'autre.
 */
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
                /*
                 * APPROUVÉ MAIS PAS FACTURÉ, et c'est le cas qui compte. Confondre les deux états
                 * ferait prélever à l'acceptation plutôt qu'à la clôture — c'est-à-dire avant que
                 * le travail soit fait.
                 */
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
