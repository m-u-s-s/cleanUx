<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\OrderEngine\HourlyRateResolver;
use App\Support\Pricing\HourlyRuleText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** L'HORLOGE D'UNE MISSION VENDUE AU TEMPS — et la seule autorité sur ce qu'elle coûte. */
class HourlyMissionClock
{
    public function __construct(
        private readonly HourlyRateResolver $rates,
    ) {}

    /**
     * L'état de l'horloge, tel que les deux surfaces le lisent.
     *
     * @return array<string, mixed>
     */
    public function etat(Mission $mission, ?Carbon $maintenant = null): array
    {
        $maintenant ??= now();
        $booking = $mission->booking;
        $achetees = $this->minutesAchetees($booking);

        // UN SEUL DISCRIMINANT GOUVERNE, et c'est celui qui décide de l'argent.
        if ($booking === null || $achetees === null || $mission->actual_start_at === null) {
            return ['applies' => false];
        }

        if (! $this->rates->seFactureALHeure($booking)) {
            return ['applies' => false];
        }

        $debut = $mission->actual_start_at;
        $echeance = $debut->copy()->addMinutes($achetees);
        $franchise = $this->franchiseEnMinutes();

        // UNE MISSION TERMINÉE A UNE HORLOGE ARRÊTÉE.
        $fin = $mission->actual_end_at;

        if ($fin !== null && $fin->lessThan($maintenant)) {
            $maintenant = $fin;
        }

        $ecoulees = max(0, (int) round(abs($debut->diffInSeconds($maintenant)) / 60));
        $depassement = max(0, $ecoulees - $achetees);

        $facturables = $this->minutesFacturables($depassement, $achetees);
        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        return [
            'applies' => true,
            // L'HEURE DU SERVEUR AU MOMENT DE LA RÉPONSE — l'ancre qui rend le compteur honnête.
            'server_now' => $maintenant->toIso8601String(),
            'started_at' => $debut->toIso8601String(),
            'purchased_minutes' => $achetees,
            // L'ÉCHÉANCE ET LE DÉBUT DE FACTURATION SONT DEUX DATES DISTINCTES, et le client doit voir les deux : la première dit « votre temps est écoulé », la seconde « à partir d'ici, ça coûte ».
            'deadline_at' => $echeance->toIso8601String(),
            'billable_from_at' => $echeance->copy()->addMinutes($franchise)->toIso8601String(),
            'grace_minutes' => $franchise,
            'elapsed_minutes' => $ecoulees,
            // Peut être NÉGATIF : c'est ce qui distingue « il reste 20 min » de « on déborde de 20 ».
            'remaining_minutes' => $achetees - $ecoulees,
            'overrun_minutes' => $depassement,
            'billable_overtime_minutes' => $facturables,
            'cap_minutes' => $this->plafondEnMinutes($achetees),
            'capped' => $depassement > $this->plafondEnMinutes($achetees) + $franchise,
            'overtime_multiplier' => $this->multiplicateur(),
            'effective_hourly_rate_cents' => $tarif,
            'overtime_amount_cents' => $this->montantDuDepassement($booking, $facturables),
            // LA RÈGLE, SERVIE AVEC LE COMPTEUR — même principe que le texte de consentement du contrôle facial : une seule source, `lang/<code>/pricing.php`, et les deux surfaces affichent la même phrase.
            'rule' => [
                'short' => HourlyRuleText::courte(),
                'provider' => HourlyRuleText::prestataire(),
            ],
        ];
    }

    /** Ce que le dépassement coûte à cet instant, en centimes. */
    public function montantDuDepassement(Booking $booking, int $minutesFacturables): int
    {
        if ($minutesFacturables <= 0) {
            return 0;
        }

        $tarif = $this->rates->tarifEffectifDeLaReservation($booking);

        if ($tarif === null || $tarif <= 0) {
            return 0;
        }

        return (int) round($tarif * ($minutesFacturables / 60) * $this->multiplicateur());
    }

    /** Les minutes réellement facturables : franchise déduite, arrondies au quart d'heure ENTAMÉ, puis plafonnées. */
    public function minutesFacturables(int $depassement, int $achetees): int
    {
        $reste = $depassement - $this->franchiseEnMinutes();

        if ($reste <= 0) {
            return 0;
        }

        $pas = max(1, (int) Config::get('order_engine.overtime_billing_increment_minutes', 15));
        $arrondies = (int) (ceil($reste / $pas) * $pas);

        return min($arrondies, $this->plafondEnMinutes($achetees));
    }

    /** LE PLAFOND BORNE L'ABUS. */
    public function plafondEnMinutes(int $achetees): int
    {
        $ratio = (float) Config::get('order_engine.overtime_cap_ratio', 1.0);

        return (int) round($achetees * max(0.0, $ratio));
    }

    /** Le temps acheté sur cette réservation, ou `null` si elle n'est pas vendue au temps. */
    public function minutesAchetees(?Booking $booking): ?int
    {
        if ($booking === null) {
            return null;
        }

        $minutes = $booking->purchased_minutes;

        return $minutes !== null && (int) $minutes > 0 ? (int) $minutes : null;
    }

    private function franchiseEnMinutes(): int
    {
        return max(0, (int) Config::get('order_engine.overtime_grace_minutes', 15));
    }

    private function multiplicateur(): float
    {
        return (float) Config::get('order_engine.overtime_multiplier', 1.30);
    }
}
