<?php

namespace Tests\Feature\Dispatch\Concerns;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;

/**
 * OUVRIR UN MÉTIER DANS UNE ZONE — le geste que les fixtures oubliaient.
 *
 * `trade_zone_pricing` est la source unique de l'activation, et l'ABSENCE DE LIGNE VAUT « FERMÉ ».
 * Une fixture qui fabrique une zone et un métier sans poser cette ligne décrit donc un service que
 * la plateforme ne vend nulle part — et le moteur a raison de ne proposer la course à personne.
 *
 * Sans ce trait, chaque test de répartition redéclarait la ligne à la main, ou l'oubliait et
 * passait pour une mauvaise raison : le jour où le verrou catalogue a été posé côté `CandidateFinder`,
 * trente-cinq tests ont basculé d'un coup, tous parce que leurs fixtures décrivaient un catalogue
 * vide. Le tarif importe peu ici ; ce qui compte, c'est que le couple existe et soit actif.
 */
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
