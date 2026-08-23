<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\PromoCodeRedemption;

/** LE PRIX RÉVISÉ — le prestataire annonce un SERVICE, le serveur en tire un TOTAL. */
class QuoteRevisionPricing
{
    /**
     * @return array{total_cents: int, breakdown: array<string, mixed>}
     */
    public function recalculer(Booking $booking, int $prixServiceCents): array
    {
        $base = max(0, $prixServiceCents);

        $promo = $this->remisePromo($booking, $base);
        $autres = (int) round(((float) ($booking->discount_amount ?? 0)) * 100);

        // Aucune remise ne peut dépasser le service : un total négatif deviendrait un remboursement
        // que personne n'a décidé.
        $remise = min($base, max(0, $promo['discount_cents'] + max(0, $autres)));

        return [
            'total_cents' => $base - $remise,
            'breakdown' => [
                'service_cents' => $base,
                'promo' => $promo['code'] === null ? null : $promo,
                'other_discount_cents' => max(0, $autres),
                'total_discount_cents' => $remise,
                'total_cents' => $base - $remise,
                'currency' => strtoupper((string) ($booking->currency ?: 'EUR')),
            ],
        ];
    }

    /**
     * @return array{code: ?string, type: ?string, value: ?float, discount_cents: int}
     */
    private function remisePromo(Booking $booking, int $base): array
    {
        $vide = ['code' => null, 'type' => null, 'value' => null, 'discount_cents' => 0];

        /** @var PromoCodeRedemption|null $rachat */
        $rachat = PromoCodeRedemption::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'applied')
            ->latest('id')
            ->first();

        $code = $rachat?->promoCode;

        if ($code === null) {
            return $vide;
        }

        $valeur = (float) ($code->discount_value ?? 0);

        // `free_first_booking` en dernier recours plutot qu'en branche nommee : le type est un
        // enum a trois valeurs, et une branche explicite pour la troisieme laisserait un `default`
        // que rien ne peut atteindre.
        $remise = match ((string) $code->discount_type) {
            'percent' => (int) round($base * $valeur / 100),
            'fixed_amount' => (int) round($valeur * 100),
            default => $base,
        };

        // Le plafond du code, quand il en porte un : un « -50 % jusqu'à 30 € » ne doit pas devenir
        // 150 € de remise parce que la prestation a grossi.
        $plafond = $code->max_discount_amount !== null
            ? (int) round(((float) $code->max_discount_amount) * 100)
            : null;

        if ($plafond !== null && $plafond > 0) {
            $remise = min($remise, $plafond);
        }

        return [
            'code' => (string) $code->code,
            'type' => (string) $code->discount_type,
            'value' => $valeur,
            'discount_cents' => min($base, max(0, $remise)),
        ];
    }
}
