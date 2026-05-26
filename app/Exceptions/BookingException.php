<?php

namespace App\Exceptions;

class BookingException extends \RuntimeException
{
    public static function slotUnavailable(): self
    {
        return new self('The requested time slot is no longer available.', 409);
    }

    public static function alreadyCancelled(): self
    {
        return new self('This booking has already been cancelled.', 422);
    }

    public static function notCancellable(): self
    {
        return new self('This booking cannot be cancelled in its current state.', 422);
    }

    public static function providerNotAssigned(): self
    {
        return new self('No provider is assigned to this booking.', 422);
    }
}
