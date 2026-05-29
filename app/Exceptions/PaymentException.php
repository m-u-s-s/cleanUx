<?php

namespace App\Exceptions;

class PaymentException extends \RuntimeException
{
    public static function chargeDeclined(): self
    {
        return new self('The payment was declined by the card issuer.', 402);
    }

    public static function insufficientFunds(): self
    {
        return new self('Insufficient funds on the payment method.', 402);
    }

    public static function stripeApiError(string $stripeMessage): self
    {
        return new self('Payment processor error: '.$stripeMessage, 502);
    }

    public static function noPaymentMethod(): self
    {
        return new self('No valid payment method found for this user.', 422);
    }
}
