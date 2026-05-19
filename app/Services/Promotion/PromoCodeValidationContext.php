<?php

namespace App\Services\Promotion;

use App\Models\User;

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
        public readonly string $currency = 'EUR',
        public readonly array $extra = [],
    ) {}
}
