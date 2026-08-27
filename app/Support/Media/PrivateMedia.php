<?php

namespace App\Support\Media;

use Illuminate\Support\Facades\URL;

/** M3 — helper for serving mission/dispute photos that now live on the PRIVATE disk. */
class PrivateMedia
{
    /**
     * DURÉE DE VIE DU LIEN SERVI À UN APPAREIL.
     *
     * Plus courte que celle du web (30 min) parce qu'il porte sa seule preuve : là où le lien web
     * exige EN PLUS une session, celui-ci vaut pour qui le détient. Quinze minutes suffisent à
     * afficher une galerie et bornent ce qu'un lien échappé peut ouvrir.
     */
    public const MINUTES_APPAREIL = 15;

    /** Signed, time-limited URL to view a private-disk file. Accepts a raw path string, or null. */
    public static function url(?string $path, int $minutes = 30): ?string
    {
        return self::signer('media.private.show', $path, $minutes);
    }

    /**
     * LE MÊME FICHIER, POUR UN CLIENT QUI N'A PAS DE SESSION.
     *
     * Une balise `Image` d'un téléphone n'envoie ni cookie ni en-tête d'autorisation : mesuré, le
     * lien web lui rend `302 → /login`, et l'application affichait donc une page de connexion en
     * guise de photo. Ce lien-ci porte sa preuve — signature HMAC sur l'URL entière, chemin et
     * expiration compris, exactement le modèle d'une URL pré-signée d'un stockage objet.
     *
     * IL NE SE DONNE QU'À QUI Y A DROIT : c'est la réponse d'API, elle authentifiée, qui décide de
     * l'émettre. Le lien n'ouvre rien d'autre que le chemin qu'il nomme.
     */
    public static function urlPourAppareil(?string $path, int $minutes = self::MINUTES_APPAREIL): ?string
    {
        return self::signer('media.private.device', $path, $minutes);
    }

    private static function signer(string $route, ?string $path, int $minutes): ?string
    {
        $path = $path !== null ? ltrim(trim($path), '/') : null;

        if ($path === null || $path === '') {
            return null;
        }

        return URL::temporarySignedRoute($route, now()->addMinutes($minutes), ['path' => $path]);
    }
}
