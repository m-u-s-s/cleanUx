<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Scalar-string cast that stores the value ENCRYPTED at rest (L7) but reads legacy *plaintext* values gracefully — so enabling the cast on a column that already holds plaintext does not break existing rows (avoids DecryptException during the transition before the backfill runs).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedStringFallback implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            // not encrypted — legacy plaintext
            return (string) $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Crypt::encryptString((string) $value);
    }
}
