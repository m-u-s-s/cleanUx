<?php

namespace App\Exceptions;

class InsuranceException extends \RuntimeException
{
    public static function planNotAvailableForBooking(): self
    {
        return new self('The selected insurance plan is not available for this booking.', 422);
    }

    public static function alreadyInsured(): self
    {
        return new self('This booking already has an active insurance policy.', 409);
    }

    public static function claimNotAllowed(string $reason): self
    {
        return new self('Cannot file claim: ' . $reason, 422);
    }

    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self("Cannot transition insurance claim from '{$from}' to '{$to}'.", 422);
    }
}
