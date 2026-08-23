<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Array cast that stores the value ENCRYPTED at rest (L7), but reads legacy *plaintext* JSON gracefully — so enabling the cast on a column that already holds unencrypted JSON does not break existing rows (avoids DecryptException during the transition before the backfill runs).
 *
 * @implements CastsAttributes<array<array-key, mixed>|null, array<array-key, mixed>|null>
 */
class EncryptedArrayFallback implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<array-key, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Try to decrypt (new/backfilled rows); fall back to raw JSON (legacy plaintext rows).
        try {
            $value = Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // not encrypted — treat as legacy plaintext JSON
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString((string) json_encode($value));
    }
}
