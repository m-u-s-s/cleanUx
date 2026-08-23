<?php

namespace App\Services\Promotion;

use App\Models\User;
use App\Support\International\Devise;

class PromoCodeValidationContext
{
    public function __construct(
        public readonly User $user,
        public readonly float $bookingAmount = 0.0,
        public readonly ?int $tradeId = null,
        public readonly ?int $serviceCatalogId = null,
        public readonly ?int $countryId = null,
        public readonly ?int $serviceZoneId = null,
        public readonly bool $isFirstBooking = false,
        public readonly bool $isB2B = false,
        public readonly array $extra = [],
        // PROMUE MAIS PAS FIGEE : une valeur par defaut ne peut pas appeler de fonction, et `'EUR'` en dur aurait valide un code promo « -10 EUR » contre un panier en dirhams.
        ?string $currency = null,
    ) {
        $this->currency = Devise::premiereRenseignee($currency);
    }

    public readonly string $currency;
}
