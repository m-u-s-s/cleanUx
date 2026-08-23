<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\URL;

/** M3 — helper for serving mission/dispute photos that now live on the PRIVATE disk. */
class PrivateMedia
{
    /** Signed, time-limited URL to view a private-disk file. Accepts a raw path string, or null. */
    public static function url(?string $path, int $minutes = 30): ?string
    {
        $path = $path !== null ? ltrim(trim($path), '/') : null;
        if ($path === null || $path === '') {
            return null;
        }

        return URL::temporarySignedRoute(
            'media.private.show',
            now()->addMinutes($minutes),
            ['path' => $path],
        );
    }
}
