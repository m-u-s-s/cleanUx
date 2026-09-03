<?php

namespace App\Services\PeerRental;

use App\Models\PeerVehicle;
use App\Services\Commission\ContexteDeCommission;
use App\Services\Commission\ResolveurDeCommission;
use App\Services\Payments\CommissionService;
use App\Services\PeerRental\Contracts\Louable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * LE PRIX D'UNE LOCATION, JOUR PAR JOUR.
 *
 * Le tarif de base est celui de l'annonce ; un week-end et une haute saison le majorent, une
 * duree longue le degresse. Le calcul est fait JOUR PAR JOUR et non sur une moyenne : un
 * sejour a cheval sur un week-end coute ce que valent ses jours, pas ce que vaudrait leur
 * moyenne — et le devis affiche se retrouve a l'euro pres dans le prelevement.
 *
 * IL IGNORE CE QU'IL CHIFFRE. Chaque bien declare ses propres supplements — une livraison pour
 * une voiture, un menage et des voyageurs pour un logement. Les demander au bien plutot que de
 * les enumerer ici est ce qui permet d'ajouter un troisieme type sans rouvrir ce fichier.
 *
 * ET LA COMMISSION SUIT LE TYPE DE BIEN : le risque d'un logement n'est pas celui d'une voiture.
 */
class PeerPricing
{
    public function __construct(private ?CommissionService $commissions = null)
    {
        $this->commissions ??= app(CommissionService::class);
    }

    /**
     * @param  array{livraison?: bool, assurance?: string|null}  $options
     * @return array{
     *   days: int,
     *   daily_price_cents: int,
     *   subtotal_cents: int,
     *   discount_cents: int,
     *   discount_percent: int,
     *   delivery_cents: int,
     *   supplements: array<string, int>,
     *   supplements_cents: int,
     *   insurance_cents: int,
     *   total_cents: int,
     *   deposit_cents: int,
     *   included_km: int,
     *   platform_fee_cents: int,
     *   owner_payout_cents: int,
     *   commission_rate: float,
     *   currency: string,
     *   detail_par_jour: list<array{date: string, cents: int, majoration: string|null}>
     * }
     */
    public function devis(
        Louable&Model $bien,
        CarbonInterface $debut,
        CarbonInterface $fin,
        array $options = [],
    ): array {
        $jours = app(PeerAvailability::class)->joursEntre($debut, $fin);
        $detail = $this->detailParJour($bien, $debut, $jours);

        $sousTotal = array_sum(array_column($detail, 'cents'));
        $degressif = $bien->remisePourDuree($jours);
        $remise = (int) round($sousTotal * $degressif / 100);

        // CHAQUE BIEN DIT CE QU'IL FACTURE EN PLUS. La livraison reste nommee a part dans le
        // devis : tout le module vehicules la lit sous ce nom.
        $supplements = $bien->lignesSupplementaires($jours, $options);
        $livraison = (int) ($supplements['livraison'] ?? 0);

        $assurance = $this->assuranceCents($options['assurance'] ?? null, $jours);

        $total = max(0, $sousTotal - $remise + array_sum($supplements) + $assurance);

        // LE MEME PARTAGE QUE PARTOUT AILLEURS, avec le taux propre a la location.
        $partage = $this->commissions->calculateForAmount(
            $total,
            null,
            $bien->devise(),
            $this->tauxDeCommission($bien->typeDeBien()),
        );

        return [
            'days' => $jours,
            'daily_price_cents' => $bien->prixJournalierCents(),
            'subtotal_cents' => $sousTotal,
            'discount_cents' => $remise,
            'discount_percent' => $degressif,
            'delivery_cents' => $livraison,
            'supplements' => $supplements,
            'supplements_cents' => array_sum($supplements),
            'insurance_cents' => $assurance,
            'total_cents' => $total,
            'deposit_cents' => $bien->cautionCents(),
            // PROPRE AUX VEHICULES : un logement n'a pas de kilometrage. Zero se lit « sans objet ».
            'included_km' => $bien instanceof PeerVehicle ? $bien->included_km_per_day * $jours : 0,
            'platform_fee_cents' => $partage['platform_fee_cents'],
            'owner_payout_cents' => $partage['provider_payout_cents'],
            'commission_rate' => $partage['commission_rate'],
            'currency' => $bien->devise(),
            'detail_par_jour' => $detail,
        ];
    }

    /**
     * @return list<array{date: string, cents: int, majoration: string|null}>
     */
    public function detailParJour(Louable&Model $bien, CarbonInterface $debut, int $jours): array
    {
        $regles = $bien->reglesDePrix();
        $weekend = (float) ($regles['weekend_multiplier'] ?? config('peer_rental.pricing.weekend_multiplier', 1.15));
        $saison = (float) ($regles['high_season_multiplier'] ?? config('peer_rental.pricing.high_season_multiplier', 1.20));
        /** @var list<int> $moisHauts */
        $moisHauts = $regles['high_season_months'] ?? config('peer_rental.pricing.high_season_months', []);

        $detail = [];
        $jour = Carbon::parse($debut->toDateString());

        for ($i = 0; $i < $jours; $i++) {
            $multiplicateur = 1.0;
            $majoration = null;

            if ($jour->isSaturday() || $jour->isSunday()) {
                $multiplicateur = max($multiplicateur, $weekend);
                $majoration = 'week-end';
            }

            if (in_array((int) $jour->month, $moisHauts, true)) {
                // LE PLUS FORT L'EMPORTE, ils ne se multiplient pas : un samedi de juillet
                // paierait sinon deux majorations pour une seule journee.
                if ($saison > $multiplicateur) {
                    $multiplicateur = $saison;
                    $majoration = 'haute saison';
                }
            }

            $detail[] = [
                'date' => $jour->toDateString(),
                'cents' => (int) round($bien->prixJournalierCents() * $multiplicateur),
                'majoration' => $majoration,
            ];

            $jour->addDay();
        }

        return $detail;
    }

    /** Le degressif du sejour : le palier le plus haut atteint, jamais leur somme. */
    /** Le bien porte desormais son propre degressif ; cette methode delegue. */
    public function degressifPercent(Louable&Model $bien, int $jours): int
    {
        return $bien->remisePourDuree($jours);
    }

    /**
     * LA COMMISSION SUIT LE TYPE DE BIEN, ET SEULEMENT S'IL EN A UN PROPRE.
     *
     * Le risque d'un logement n'est pas celui d'une voiture, et les places de marche du secteur
     * l'ont toutes tranche ainsi. Sans reglage dedie, le taux general s'applique : aucune decision
     * n'est forcee aujourd'hui, et rien ne se casse le jour ou on en prendra une.
     *
     * Le taux est FIGE sur chaque location au moment du devis : le changer n'altere aucune
     * location deja conclue.
     */
    public function tauxDeCommission(?string $typeDeBien = null): float
    {
        // LE TAUX REGLE PAR LE SUPER-ADMINISTRATEUR D'ABORD. Sans regle qui couvre le cas,
        // le resolveur rend exactement le taux de `config/peer_rental.php` : brancher ce
        // socle ne change le prix d'aucune location tant que rien n'est regle.
        $reglee = app(ResolveurDeCommission::class)->pour(
            ContexteDeCommission::locationEntreMembres($typeDeBien),
        );

        if ($reglee->regle !== null) {
            return $reglee->taux;
        }

        $general = (int) config('peer_rental.commission_percent', 25);

        $propre = $typeDeBien === null
            ? null
            : config('peer_rental.commission_percent_par_type.'.$typeDeBien);

        return max(0, (int) ($propre ?? $general)) / 100;
    }

    /** COQUILLE — aucun assureur partenaire n'est contractualise, le tarif reste indicatif. */
    private function assuranceCents(?string $formule, int $jours): int
    {
        if ($formule === null || ! config('peer_rental.insurance.enabled', false)) {
            return 0;
        }

        /** @var array<string, array{label: string, daily_cents: int, franchise_cents: int}> $formules */
        $formules = config('peer_rental.insurance.plans', []);

        return (int) (($formules[$formule]['daily_cents'] ?? 0) * $jours);
    }
}
