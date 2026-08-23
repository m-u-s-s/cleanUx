<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** LE SEUL ENDROIT QUI ÉCRIT UNE IMAGE DE VISAGE. Deux règles, et elles ne se négocient pas : 1. */
class FaceImageStore
{
    public function putReference(User $user, string $contents, string $mimeType = 'image/jpeg'): string
    {
        return $this->put("providers/{$user->id}/face/reference-".Str::random(12).'.enc', $contents);
    }

    public function putSelfie(ProviderFaceCheck $check, string $contents, string $mimeType = 'image/jpeg'): string
    {
        return $this->put(
            "providers/{$check->user_id}/face/checks/{$check->id}-".Str::random(12).'.enc',
            $contents
        );
    }

    public function get(?string $path): ?string
    {
        if (! filled($path) || ! $this->disk()->exists($path)) {
            return null;
        }

        $brut = $this->disk()->get($path);

        if ($brut === null || $brut === '') {
            return null;
        }

        try {
            return Crypt::decryptString($brut);
        } catch (\Throwable $e) {
            // Un fichier illisible n'est pas une panne à propager : c'est une référence perdue.
            Log::warning('[face_check] image de visage illisible', ['path' => $path]);

            return null;
        }
    }

    public function forget(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        try {
            return $this->disk()->delete($path);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    public function fingerprint(string $contents): string
    {
        return hash('sha256', $contents);
    }

    private function put(string $path, string $contents): string
    {
        $this->disk()->put($path, Crypt::encryptString($contents));

        return $path;
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('face_check.disk', 'private'));
    }
}
