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
            // MATCH SUR `cle_de_reference`, PAS `declencheur` : un administrateur a le droit de
            // poser sa propre règle sur le même événement (deux règles, deux conditions, c'est
            // l'intérêt du moteur) — `declencheur` n'est donc pas une identité fiable pour NOUS.
            // `cle_de_reference` n'appartient qu'au seeder ; une règle admin la laisse NULL, et un
            // index unique nullable admet plusieurs NULL (mesuré MySQL et SQLite), donc aucune
            // collision entre elles.
            $existante = AutomationRule::query()
                ->where('cle_de_reference', $regle['cle_de_reference'])
                ->first();

            if ($existante !== null) {
                // CRÉATION SEULE, JAMAIS DE MISE À JOUR : `ConstructeurDeRegle` expose CHAQUE
                // champ de cette liste à un administrateur (nom, description, quota, plafond,
                // politique, conditions, actions...). Un redéploiement qui rejouerait ce seeder
                // ne doit écraser ni un renommage, ni un quota ajusté, ni — surtout — faire
                // régresser l'état d'une règle déjà observée ou armée.
                continue;
            }

            // `forceCreate`, pas `create` : `cle_de_reference` est hors `$fillable` par choix
            // (aucun formulaire admin ne doit la poser), donc un simple `create()` la rejetterait.
            AutomationRule::query()->forceCreate($regle);
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
                description: "Surveille l'échec de capture du solde d'une réservation, pour qu'un administrateur en soit informé — pas seulement le client. L'acompte, débité à la commande sur sa propre intention Stripe, n'est pas couvert : son échec ne dit rien du solde.",
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
                description: "Cette alerte n'est pas encore émise : rien ne mesure aujourd'hui la profondeur de la file de webhooks sortants. La règle est prête et agira le jour où une source l'émettra ; d'ici là, elle ne peut ni s'observer, ni s'armer.",
                message: 'La file de webhooks sortants déborde.',
            ),
            $this->regle(
                cle: 'stuck_mission_holding_funds',
                nom: 'Mission bloquée retenant des fonds',
                description: "Cette alerte n'est pas encore émise : rien ne détecte aujourd'hui une mission bloquée qui retient les fonds d'un client. La règle est prête et agira le jour où une source l'émettra ; d'ici là, elle ne peut ni s'observer, ni s'armer.",
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
            // L'identité DU SEEDER, distincte du déclencheur qu'un admin peut réutiliser.
            'cle_de_reference' => "systeme.alerte_metier.{$cle}",
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
