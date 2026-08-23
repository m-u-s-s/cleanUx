<?php

namespace App\Services\Safety\Contracts;

use App\Models\Booking;
use App\Models\User;
use App\Services\Safety\Data\MaskedCallSessionData;

/** Contract for anonymous proxy call providers (Twilio, Vonage, mock…). */
interface MaskedCallProvider
{
    /**
     * Create a masked proxy session between caller and receiver.
     *
     * @param  User  $caller  The party that initiates the call (usually client)
     * @param  User  $receiver  The other party (usually provider)
     * @param  Booking|null  $booking  Associated booking for idempotency / TTL
     * @return MaskedCallSessionData Contains the proxy phone number and session reference
     */
    public function createMaskedSession(
        User $caller,
        User $receiver,
        ?Booking $booking = null,
    ): MaskedCallSessionData;

    /**
     * Terminate the proxy session identified by its external reference.
     *
     * @param  string  $externalRef  Provider-side session identifier
     */
    public function closeSession(string $externalRef): void;

    /** Provider identifier string (used in config and logs). */
    public function name(): string;
}
