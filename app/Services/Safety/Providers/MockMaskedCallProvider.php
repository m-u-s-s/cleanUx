<?php

namespace App\Services\Safety\Providers;

use App\Models\Booking;
use App\Models\User;
use App\Services\Safety\Contracts\MaskedCallProvider;
use App\Services\Safety\Data\MaskedCallSessionData;
use Illuminate\Support\Str;

/**
 * Fake proxy provider for local development and testing.
 *
 * Generates a deterministic-looking Belgian proxy number and a UUID
 * as the external reference. No real API calls are made.
 */
class MockMaskedCallProvider implements MaskedCallProvider
{
    public function createMaskedSession(
        User $caller,
        User $receiver,
        ?Booking $booking = null,
    ): MaskedCallSessionData {
        $proxyPhone = $this->generateMockProxyNumber();
        $ref = 'mock_' . Str::uuid()->toString();

        $ttlHours = (int) config('masked_calls.session_ttl_hours', 24);
        $expiresAt = now()->addHours($ttlHours)->toIso8601String();

        return new MaskedCallSessionData(
            proxyPhoneNumber: $proxyPhone,
            externalRef: $ref,
            expiresAt: $expiresAt,
            metadata: [
                'provider' => 'mock',
                'caller_id' => $caller->id,
                'receiver_id' => $receiver->id,
                'booking_id' => $booking?->id,
            ],
        );
    }

    public function closeSession(string $externalRef): void
    {
        // No-op for mock provider
    }

    public function name(): string
    {
        return 'mock';
    }

    private function generateMockProxyNumber(): string
    {
        // Belgian mobile proxy range (+32 4XX XXX XXX)
        $suffix = str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
        return '+324' . $suffix;
    }
}
