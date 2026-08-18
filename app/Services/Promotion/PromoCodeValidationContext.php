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
        /*
         * PROMUE MAIS PAS FIGEE : une valeur par defaut ne peut pas appeler de fonction, et
         * `'EUR'` en dur aurait valide un code promo « -10 EUR » contre un panier en dirhams.
         * `null` demande la devise de la plateforme ; les appelants qui connaissent celle de la
         * reservation la passent.
         *
         * Le parametre a change de PLACE en meme temps que de type : il etait avant `$extra`, et
         * l'y laisser aurait fait passer silencieusement un tableau dans un argument devenu
         * nullable chez tout appelant positionnel. Les deux appelants existants nomment leurs
         * arguments -- verifie avant de deplacer.
         */
        ?string $currency = null,
    ) {
        $this->currency = Devise::premiereRenseignee($currency);
    }

    public readonly string $currency;
}
