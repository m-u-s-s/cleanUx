<?php

namespace Tests\Feature\Dispatch\Concerns;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;

/** OUVRIR UN MÉTIER DANS UNE ZONE — le geste que les fixtures oubliaient. */
trait OuvreLeCatalogue
{
    protected function ouvrirAuCatalogue(Trade $trade, ServiceZone $zone, bool $immediat = true): TradeZonePricing
    {
        return TradeZonePricing::updateOrCreate(
            ['trade_id' => $trade->id, 'service_zone_id' => $zone->id],
            [
                'base_rate_cents' => 5000,
                'surge_multiplier' => '1.00',
                'is_active' => true,
                'asap_enabled' => $immediat,
            ],
        );
    }
}
