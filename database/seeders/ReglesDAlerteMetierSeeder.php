<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use Illuminate\Database\Seeder;

/**
 * Les cinq alertes du chemin de l'argent, rendues lisibles par un administrateur.
 *
 * Chaque règle naît en `brouillon` : `EtatDeRegle::armer()` refuse une règle sans journal
 * d'observation, et un seeder ne peut pas fabriquer d'observations — seul un passage réel le peut.
 */
class ReglesDAlerteMetierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->regles() as $regle) {
            // CRÉATION SEULE, JAMAIS DE MISE À JOUR : `ConstructeurDeRegle` expose CHAQUE champ
            // de cette liste à un administrateur (nom, description, quota, plafond, politique,
            // conditions, actions...). Un redéploiement qui rejouerait ce seeder ne doit écraser
            // ni un renommage, ni un quota ajusté, ni — surtout — faire régresser l'état d'une
            // règle déjà observée ou armée. Seule l'absence de la ligne (première installation)
            // déclenche une écriture.
            AutomationRule::query()->firstOrCreate(
                ['declencheur' => $regle['declencheur']],
                $regle
            );
        }

        $this->command?->info('✅ Cinq règles d\'alerte métier semées, toutes en brouillon.');
    }

    /** @return list<array<string, mixed>> */
    protected function regles(): array
    {
        return [
            $this->regle(
                cle: 'payment_capture_failed',
                nom: 'Capture de paiement en échec',
                description: "Surveille les captures de paiement client qui échouent, pour qu'un administrateur en soit informé — pas seulement le client.",
                message: "La capture d'un paiement a échoué.",
            ),
            $this->regle(
                cle: 'payout_failed',
                nom: 'Versement prestataire en échec',
                description: "Surveille les versements aux prestataires qui échouent côté Stripe — aujourd'hui sans aucune notification interne.",
                message: 'Un versement prestataire a échoué.',
            ),
            $this->regle(
                cle: 'webhook_backlog',
                nom: 'File de webhooks qui déborde',
                description: 'Surveille le débordement de la file de webhooks sortants, avant que la file ne devienne invisible.',
                message: 'La file de webhooks sortants déborde.',
            ),
            $this->regle(
                cle: 'stuck_mission_holding_funds',
                nom: 'Mission bloquée retenant des fonds',
                description: "Surveille les missions bloquées qui retiennent encore les fonds d'un client.",
                message: 'Une mission bloquée retient des fonds.',
            ),
            $this->regle(
                cle: 'reconciliation_divergence',
                nom: 'Réconciliation qui diverge',
                description: 'Surveille les divergences détectées par la réconciliation Stripe, au-delà du seul écran admin.',
                message: 'La réconciliation a détecté une divergence.',
            ),
        ];
    }

    /** @return array<string, mixed> */
    protected function regle(string $cle, string $nom, string $description, string $message): array
    {
        return [
            'nom' => $nom,
            'description' => $description,
            'entite' => 'alerte',
            'declencheur' => "alerte.{$cle}",
            'cadence' => null,
            'conditions' => ['field' => 'cle', 'op' => 'eq', 'value' => $cle],
            'actions' => [[
                'cle' => 'notifier.admins',
                'parametres' => ['message' => $message],
            ]],
            // Une alerte est un fait distinct à chaque fois : le rejouer indéfiniment spamerait
            // les admins tant que la ligne resterait éligible. Une fois par occurrence suffit.
            'politique_reprise' => 'une_fois',
            // Explicite malgré le défaut du modèle : aucun test ne tombe si on l'enlève
            // aujourd'hui (le défaut vaut déjà 'brouillon'), mais la garde du brief est absolue.
            'etat' => AutomationRule::ETAT_BROUILLON,
            // Alertes d'argent, pas un balayage de masse : quota et plafond nettement sous les
            // défauts génériques (50/500), assez larges pour absorber une vraie panne groupée.
            'quota_par_passage' => 20,
            'plafond_journalier' => 200,
        ];
    }
}
