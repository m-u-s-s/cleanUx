<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * LE SEUL ENDROIT QUI ÉCRIT UNE IMAGE DE VISAGE.
 *
 * Deux règles, et elles ne se négocient pas :
 *
 * 1. **Disque privé.** La photo de profil du prestataire, elle, part sur le disque `public` et se
 *    lit sans authentification via `/storage/…` — c'est aujourd'hui le seul portrait stocké de la
 *    plateforme, et il est en accès libre. Une donnée biométrique ne peut pas suivre ce chemin.
 *
 * 2. **Chiffrée au repos.** Le disque privé protège de l'URL, pas de la sauvegarde qu'on recopie,
 *    du volume qu'on monte ailleurs, ni de l'exfiltration d'un dossier. Un visage relève de
 *    l'article 9 du RGPD : il est chiffré avec la clé applicative, comme les colonnes sensibles du
 *    KYC. Un fichier récupéré sans la clé ne montre rien.
 *
 * En conséquence, un fichier écrit ici ne s'ouvre PAS avec une visionneuse : il se lit par `get()`,
 * et lui seul. C'est voulu.
 */
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
            /*
             * Un fichier illisible n'est pas une panne à propager : c'est une référence perdue. Le
             * module en tire les conséquences plus haut (contrôle en erreur, revue manuelle), et
             * l'administrateur voit qu'il manque une image. Laisser remonter l'exception ferait
             * échouer une mise en ligne pour un fichier corrompu, sans rien expliquer.
             */
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
