<?php

namespace App\Services\PeerRental;

use App\Models\PeerVehicle;
use App\Services\Payments\CommissionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * LE PRIX D'UNE LOCATION, JOUR PAR JOUR.
 *
 * Le tarif de base est celui de l'annonce ; un week-end et une haute saison le majorent, une
 * duree longue le degresse. Le calcul est fait JOUR PAR JOUR et non sur une moyenne : un
 * sejour a cheval sur un week-end coute ce que valent ses jours, pas ce que vaudrait leur
 * moyenne — et le devis affiche se retrouve a l'euro pres dans le prelevement.
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
        PeerVehicle $vehicule,
        CarbonInterface $debut,
        CarbonInterface $fin,
        array $options = [],
    ): array {
        $jours = app(PeerAvailability::class)->joursEntre($debut, $fin);
        $detail = $this->detailParJour($vehicule, $debut, $jours);

        $sousTotal = array_sum(array_column($detail, 'cents'));
        $degressif = $this->degressifPercent($vehicule, $jours);
        $remise = (int) round($sousTotal * $degressif / 100);

        $livraison = ($options['livraison'] ?? false) && $vehicule->delivery_enabled
            ? $vehicule->delivery_price_cents
            : 0;

        $assurance = $this->assuranceCents($options['assurance'] ?? null, $jours);

        $total = max(0, $sousTotal - $remise + $livraison + $assurance);

        // LE MEME PARTAGE QUE PARTOUT AILLEURS, avec le taux propre a la location.
        $partage = $this->commissions->calculateForAmount(
            $total,
            null,
            $vehicule->currency,
            $this->tauxDeCommission(),
        );

        return [
            'days' => $jours,
            'daily_price_cents' => $vehicule->daily_price_cents,
            'subtotal_cents' => $sousTotal,
            'discount_cents' => $remise,
            'discount_percent' => $degressif,
            'delivery_cents' => $livraison,
            'insurance_cents' => $assurance,
            'total_cents' => $total,
            'deposit_cents' => $vehicule->deposit_cents,
            'included_km' => $vehicule->included_km_per_day * $jours,
            'platform_fee_cents' => $partage['platform_fee_cents'],
            'owner_payout_cents' => $partage['provider_payout_cents'],
            'commission_rate' => $partage['commission_rate'],
            'currency' => $vehicule->currency,
            'detail_par_jour' => $detail,
        ];
    }

    /**
     * @return list<array{date: string, cents: int, majoration: string|null}>
     */
    public function detailParJour(PeerVehicle $vehicule, CarbonInterface $debut, int $jours): array
    {
        $regles = $vehicule->pricing_rules ?? [];
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
                'cents' => (int) round($vehicule->daily_price_cents * $multiplicateur),
                'majoration' => $majoration,
            ];

            $jour->addDay();
        }

        return $detail;
    }

    /** Le degressif du sejour : le palier le plus haut atteint, jamais leur somme. */
    public function degressifPercent(PeerVehicle $vehicule, int $jours): int
    {
        return match (true) {
            $jours >= 28 => (int) $vehicule->discount_28_days_percent,
            $jours >= 7 => (int) $vehicule->discount_7_days_percent,
            $jours >= 3 => (int) $vehicule->discount_3_days_percent,
            default => 0,
        };
    }

    public function tauxDeCommission(): float
    {
        return max(0, (int) config('peer_rental.commission_percent', 25)) / 100;
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
