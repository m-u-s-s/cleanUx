<?php

namespace Database\Seeders;

use App\Models\AcademyCourse;
use App\Models\ProviderQuest;
use App\Models\SafetyAlert;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * LES OBJECTIFS (E13), L'ACADÉMIE (E16) ET UNE ALERTE OUVERTE (E33), SUR LA BASE DE DÉMONSTRATION.
 *
 * SANS DONNÉES, TROIS ÉCRANS S'OUVRENT SUR DU VIDE — et un écran vide ne distingue pas « rien à
 * montrer » de « la requête est fausse ». Pour les quêtes c'est pire encore : leur intérêt EST le
 * compteur, et un catalogue sans quête ne permet pas de voir qu'il fonctionne.
 *
 * L'ALERTE DE DÉMONSTRATION EST UNE VEILLE, jamais une urgence. Une urgence factice dans le centre
 * de sécurité s'y confondrait avec une vraie, et le jour où une vraie arrive on la traiterait comme
 * du décor. La veille montre le mécanisme sans crier au loup.
 *
 * IDEMPOTENT : chaque ligne est cherchée sur son code avant d'être écrite.
 */
class ProgressionPrestataireDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->quetes();
        $this->formations();
        $this->alerteDeDemonstration();

        $this->command?->info('✅ Progression prestataire : objectifs, académie et une veille de sécurité.');
    }

    /**
     * Deux objectifs : un atteignable, un de carrière.
     *
     * LES DEUX FORMES COMPTENT. « 10 missions ce mois-ci » est un objectif qu'on peut viser ;
     * « 100 missions » est un palier de carrière, sans échéance. N'en montrer qu'une donnerait à
     * croire que le module ne sait faire que ça.
     */
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

    /**
     * Deux formations, dont une qui débloque un badge.
     *
     * CE QUE ÇA RAPPORTE DOIT ÊTRE VISIBLE : un catalogue de cours sans effet est un catalogue que
     * personne n'ouvre deux fois.
     */
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

    /**
     * Une VEILLE ouverte, pour que le centre de sécurité ait quelque chose à montrer.
     *
     * JAMAIS UNE URGENCE. Une urgence factice s'y confondrait avec une vraie, et le jour où une
     * vraie arrive on la traiterait comme du décor.
     */
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
