<?php

namespace App\Services\Commission;

use App\Models\CommissionRule;
use App\Models\CommissionRuleRevision;
use App\Models\User;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * RÉGLER UN TAUX — la porte unique, et elle n'est ouverte qu'au titulaire du siège.
 *
 * Un taux de commission décide de ce que gagnent des milliers de prestataires. Trois choses le
 * tiennent :
 *
 * 1. SEUL LE TITULAIRE DU SIÈGE. Pas une capacité qu'on accorde, pas « gestion finance » : la
 *    propriété de la plateforme. C'est vérifié ici, pas seulement sur l'écran — `/livewire/update`
 *    ne rejoue aucun middleware de route.
 * 2. TOUT EST HISTORISÉ. Le taux d'avant, celui d'après, qui, quand, depuis quelle adresse. Un
 *    changement de taux qu'on ne peut pas dater ne se conteste pas.
 * 3. LE CACHE TOMBE À CHAQUE ÉCRITURE, sinon un taux réglé ne s'appliquerait qu'au bout de cinq
 *    minutes — et le super-administrateur croirait à un bug.
 */
class GestionDesCommissions
{
    /**
     * @param  array<string, mixed>  $valeurs
     *
     * @throws DomainException
     */
    public function creer(User $acteur, array $valeurs): CommissionRule
    {
        $this->exigeLeTitulaire($acteur);

        $valeurs = $this->valide($valeurs);

        return DB::transaction(function () use ($acteur, $valeurs): CommissionRule {
            $regle = CommissionRule::create($valeurs + ['updated_by' => $acteur->id]);

            $this->historise($regle, $acteur, 'created', null, (float) $regle->percent);

            return $regle;
        });
    }

    /**
     * @param  array<string, mixed>  $valeurs
     *
     * @throws DomainException
     */
    public function modifier(User $acteur, CommissionRule $regle, array $valeurs): CommissionRule
    {
        $this->exigeLeTitulaire($acteur);

        $avant = (float) $regle->percent;
        $valeurs = $this->valide($valeurs);

        return DB::transaction(function () use ($acteur, $regle, $valeurs, $avant): CommissionRule {
            $regle->forceFill($valeurs + ['updated_by' => $acteur->id])->save();

            $this->historise($regle, $acteur, 'updated', $avant, (float) $regle->percent);

            return $regle->refresh();
        });
    }

    /** @throws DomainException */
    public function supprimer(User $acteur, CommissionRule $regle): void
    {
        $this->exigeLeTitulaire($acteur);

        DB::transaction(function () use ($acteur, $regle) {
            // L'HISTORIQUE SURVIT À LA RÈGLE : c'est tout son intérêt. La clé devient nulle, la
            // trace reste.
            $this->historise($regle, $acteur, 'deleted', (float) $regle->percent, null);
            $regle->delete();
        });

        app(ResolveurDeCommission::class)->oublierLeCache();
    }

    /**
     * LA VALIDATION DE FOND — celle que l'écran ne peut pas garantir.
     *
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     *
     * @throws DomainException
     */
    private function valide(array $valeurs): array
    {
        $pourcentage = (float) ($valeurs['percent'] ?? -1);

        // DE 0 À 100, ET RIEN D'AUTRE. Un taux négatif paierait le prestataire deux fois ; un taux
        // au-dessus de cent lui ferait devoir de l'argent.
        if ($pourcentage < 0 || $pourcentage > 100) {
            throw new DomainException('Un taux de commission va de 0 à 100 %.');
        }

        if (trim((string) ($valeurs['label'] ?? '')) === '') {
            throw new DomainException('Une règle porte un nom : c’est lui qui s’affiche sur les écrans.');
        }

        $module = $valeurs['module'] ?? null;

        if ($module !== null && ! array_key_exists($module, CommissionRule::MODULES)) {
            throw new DomainException('Module inconnu : '.$module);
        }

        // UNE FENÊTRE À L'ENVERS NE S'OUVRE JAMAIS, et rien ne le dirait.
        $debut = $valeurs['starts_on'] ?? null;
        $fin = $valeurs['ends_on'] ?? null;

        if ($debut !== null && $fin !== null && $debut !== '' && $fin !== '' && $fin < $debut) {
            throw new DomainException('La date de fin précède la date de début : cette règle ne s’appliquerait jamais.');
        }

        return $valeurs;
    }

    private function historise(CommissionRule $regle, User $acteur, string $action, ?float $avant, ?float $apres): void
    {
        CommissionRuleRevision::create([
            'commission_rule_id' => $regle->id,
            'action' => $action,
            'percent_before' => $avant,
            'percent_after' => $apres,
            'snapshot' => $regle->only([
                'label', 'module', 'asset_type', 'trade_id', 'service_zone_id',
                'min_duration_days', 'starts_on', 'ends_on', 'percent', 'min_cents', 'is_active', 'priority',
            ]),
            'actor_id' => $acteur->id,
            'actor_ip' => request()->ip(),
        ]);

        ActivityLogger::critical('finance.commission_rule_'.$action, $regle, [
            'domain' => 'finance',
            'percent_before' => $avant,
            'percent_after' => $apres,
        ]);

        app(ResolveurDeCommission::class)->oublierLeCache();
    }

    /** @throws DomainException */
    private function exigeLeTitulaire(User $acteur): void
    {
        if (! $acteur->isSuperAdmin()) {
            throw new DomainException('Seul le titulaire du siège règle les commissions.');
        }
    }
}
