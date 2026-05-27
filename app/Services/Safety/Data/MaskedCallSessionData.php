<?php

namespace App\Services\Safety\Data;

/**
 * Value object returned by MaskedCallProvider::createMaskedSession().
 *
 * Carries all the information needed to persist a MaskedCallSession row
 * and display the proxy number to both parties.
 */
final class MaskedCallSessionData
{
    public function __construct(
        /** Proxy phone number both parties dial instead of each other's real number */
        public readonly string $proxyPhoneNumber,

        /** Provider-side session reference (e.g. Twilio session SID, UUID for mock) */
        public readonly string $externalRef,

        /** ISO-8601 expiry. Null means the session has no server-side TTL. */
        public readonly ?string $expiresAt = null,

        /** Extra provider metadata (raw Twilio JSON, etc.) */
        public readonly array $metadata = [],
    ) {}
}
