<?php

namespace App\Services\Workforce;

use App\Models\InventoryMovement;
use App\Models\Mission;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * LA RENTABILITÉ D'UNE MISSION, D'UN SITE, D'UNE ÉQUIPE (E22).
 *
 * Une société prestataire sait ce qu'elle facture. Elle ne sait pas ce que ça lui coûte — et donc
 * pas si elle gagne de l'argent sur ce client-là. C'est la question qui décide de renégocier un
 * contrat, de refuser une reconduction, ou de s'apercevoir qu'un site précis mange toute la marge
 * des autres.
 *
 * TROIS TERMES, ET AUCUN N'ÉTAIT DISPONIBLE AVANT CETTE PHASE.
 *
 * Le PRODUIT vient du devis de la réservation. Les HEURES viennent des pointages (E20), qui
 * n'existaient pas : on ne pouvait qu'estimer le temps passé, c'est-à-dire deviner. Les
 * CONSOMMABLES viennent des mouvements d'inventaire rattachés à la mission (E23 et F7), qui
 * n'existaient pas non plus.
 *
 * LE COÛT HORAIRE EST UNE HYPOTHÈSE, ET C'EST DIT. La plateforme ne connaît pas les salaires : le
 * taux vient de la configuration de la société, et à défaut d'un défaut prudent. Une marge calculée
 * sur un taux inventé serait plus dangereuse qu'une absence de marge — on la lirait comme un fait.
 * Le résultat porte donc `hourly_rate_source` pour que l'écran puisse le dire.
 *
 * LES MISSIONS SANS POINTAGE SONT COMPTÉES À PART, jamais fondues dans la moyenne. Une mission dont
 * on ignore les heures ferait apparaître une marge de 100 % : la masquer donnerait une rentabilité
 * flatteuse et fausse, exactement ce qu'on cherche à éviter.
 */
class ProfitabilityService
{
    /**
     * Le coût horaire retenu quand la société n'en a pas déclaré.
     *
     * Volontairement PRUDENT : surestimer le coût fait paraître une mission moins rentable qu'elle
     * ne l'est, ce qui pousse à vérifier. Le sous-estimer ferait signer des contrats à perte.
     */
    public const DEFAULT_HOURLY_COST_CENTS = 2200;

    /**
     * La rentabilité d'une mission.
     *
     * @return array<string, mixed>
     */
    public function pourLaMission(Mission $mission): array
    {
        $produitCents = (int) round(((float) ($mission->booking->devis_estime ?? 0)) * 100);

        $minutes = (int) TimeEntry::query()
            ->where('mission_id', $mission->id)
            ->whereIn('status', [TimeEntry::STATUS_RECORDED, TimeEntry::STATUS_APPROVED])
            ->sum('worked_minutes');

        $tauxCents = $this->tauxHoraireCents($mission);
        $coutMainDoeuvre = (int) round($minutes / 60 * $tauxCents);
        $coutConsommables = $this->coutDesConsommables($mission);

        $coutTotal = $coutMainDoeuvre + $coutConsommables;

        return [
            'mission_id' => $mission->id,
            'revenue_cents' => $produitCents,
            'worked_minutes' => $minutes,
            'labour_cost_cents' => $coutMainDoeuvre,
            'consumables_cost_cents' => $coutConsommables,
            'total_cost_cents' => $coutTotal,
            'margin_cents' => $produitCents - $coutTotal,
            // Le taux est une hypothèse : l'écran doit pouvoir le dire plutôt que de présenter la
            // marge comme un fait établi.
            'hourly_rate_cents' => $tauxCents,
            'hourly_rate_source' => $this->sourceDuTaux($mission),
            // Une mission sans pointage n'a pas de marge calculable : le signaler vaut mieux que
            // d'afficher 100 %.
            'has_timesheet' => $minutes > 0,
        ];
    }

    /**
     * L'agrégat d'une période pour une société, ventilé par la clé demandée.
     *
     * @param  'site'|'team'|'agency'  $par
     * @return Collection<int|string, array{key: mixed, missions_count: int<0, max>, missions_without_timesheet: int<0, max>, revenue_cents: int, total_cost_cents: int, margin_cents: int, worked_minutes: int}>
     */
    public function pourLaPeriode(
        int $organisationId,
        Carbon $debut,
        Carbon $fin,
        string $par = 'site',
    ): Collection {
        $missions = Mission::query()
            ->where('provider_organization_id', $organisationId)
            ->whereBetween('created_at', [$debut, $fin])
            ->with('booking')
            ->get();

        return $missions
            ->groupBy(fn (Mission $mission) => match ($par) {
                'team' => $mission->provider_team_id ?? $mission->field_team_id ?? 0,
                'agency' => $mission->provider_agency_id ?? 0,
                default => $mission->organization_site_id ?? 0,
            })
            ->map(function (Collection $groupe, $cle) {
                $lignes = $groupe->map(fn (Mission $mission) => $this->pourLaMission($mission));

                return [
                    'key' => $cle,
                    'missions_count' => $lignes->count(),
                    // Les missions sans pointage sont comptées À PART : les fondre dans la moyenne
                    // ferait apparaître une marge de 100 % sur chacune d'elles.
                    'missions_without_timesheet' => $lignes->where('has_timesheet', false)->count(),
                    'revenue_cents' => (int) $lignes->sum('revenue_cents'),
                    'total_cost_cents' => (int) $lignes->sum('total_cost_cents'),
                    'margin_cents' => (int) $lignes->sum('margin_cents'),
                    'worked_minutes' => (int) $lignes->sum('worked_minutes'),
                ];
            })
            ->values()
            ->keyBy('key');
    }

    /**
     * Ce que les consommables déclarés sur cette mission ont coûté.
     *
     * Les mouvements de consommation portent une quantité NÉGATIVE : on la remet en positif pour
     * valoriser. Sommer tel quel produirait un coût négatif qui viendrait gonfler la marge.
     */
    protected function coutDesConsommables(Mission $mission): int
    {
        return (int) InventoryMovement::query()
            ->where('mission_id', $mission->id)
            ->where('type', InventoryMovement::TYPE_CONSUMPTION)
            ->with('item:id,unit_cost_cents')
            ->get()
            ->sum(fn (InventoryMovement $mouvement) => abs((int) $mouvement->quantity)
                * (int) ($mouvement->item->unit_cost_cents ?? 0));
    }

    protected function tauxHoraireCents(Mission $mission): int
    {
        $declare = data_get(
            $mission->providerOrganization?->metadata,
            'workforce.hourly_cost_cents',
        );

        return is_numeric($declare) && (int) $declare > 0
            ? (int) $declare
            : self::DEFAULT_HOURLY_COST_CENTS;
    }

    protected function sourceDuTaux(Mission $mission): string
    {
        $declare = data_get(
            $mission->providerOrganization?->metadata,
            'workforce.hourly_cost_cents',
        );

        return is_numeric($declare) && (int) $declare > 0 ? 'declared' : 'default';
    }
}
