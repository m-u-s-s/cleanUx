<?php

namespace App\Services\Commission;

use App\Models\Booking;
use App\Models\CommissionRule;
use Carbon\CarbonInterface;

/**
 * CE QU'ON SAIT DE L'ARGENT QU'ON PARTAGE.
 *
 * Chaque champ répond à une question que le super-administrateur peut poser dans une règle :
 * quel module, quel type de bien, quel métier, quelle zone, quelle durée, quel jour. Tout est
 * facultatif sauf le module — un partage sans module n'existe pas.
 */
final readonly class ContexteDeCommission
{
    public function __construct(
        public string $module,
        public ?string $typeDeBien = null,
        public ?int $tradeId = null,
        public ?int $zoneId = null,
        public ?int $dureeJours = null,
        public ?CarbonInterface $date = null,
    ) {}

    public static function prestation(?int $tradeId = null, ?int $zoneId = null): self
    {
        return new self(CommissionRule::MODULE_PRESTATION, tradeId: $tradeId, zoneId: $zoneId);
    }

    /** LE MÉTIER ET LA ZONE VIENNENT DE LA RÉSERVATION : les deviner ailleurs les ferait diverger. */
    public static function pourUneReservation(Booking $booking): self
    {
        return new self(
            module: CommissionRule::MODULE_PRESTATION,
            tradeId: $booking->trade_id === null ? null : (int) $booking->trade_id,
            zoneId: $booking->service_zone_id === null ? null : (int) $booking->service_zone_id,
        );
    }

    public static function locationEntreMembres(?string $typeDeBien, ?int $dureeJours = null): self
    {
        return new self(
            CommissionRule::MODULE_LOCATION_MEMBRES,
            typeDeBien: $typeDeBien,
            dureeJours: $dureeJours,
        );
    }

    public static function pourboire(): self
    {
        return new self(CommissionRule::MODULE_POURBOIRE);
    }
}
