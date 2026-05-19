<?php

namespace App\Services\Promotion;

use App\Models\PromoCode;

class PromoCodeValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?PromoCode $promoCode = null,
        public readonly float $discountAmount = 0.0,
        public readonly float $finalAmount = 0.0,
        public readonly ?string $reason = null,
        public readonly ?string $errorCode = null,
    ) {}

    public static function ok(PromoCode $code, float $discount, float $final): self
    {
        return new self(
            valid: true,
            promoCode: $code,
            discountAmount: round($discount, 2),
            finalAmount: round($final, 2),
        );
    }

    public static function fail(string $errorCode, string $reason, ?PromoCode $code = null): self
    {
        return new self(
            valid: false,
            promoCode: $code,
            reason: $reason,
            errorCode: $errorCode,
        );
    }
}
