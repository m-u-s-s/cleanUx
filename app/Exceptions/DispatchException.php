<?php

namespace App\Exceptions;

class DispatchException extends \RuntimeException
{
    public static function noProvidersAvailable(): self
    {
        return new self('No providers are available to handle this booking.', 422);
    }

    public static function noPlannedMission(): self
    {
        return new self('No planned mission found for this booking.', 422);
    }

    public static function alreadyDispatched(): self
    {
        return new self('This booking has already been dispatched to a provider.', 409);
    }

    public static function providerIneligible(string $reason): self
    {
        return new self('Provider is ineligible for dispatch: ' . $reason, 422);
    }
}
