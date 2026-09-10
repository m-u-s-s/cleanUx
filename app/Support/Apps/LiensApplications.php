<?php

namespace App\Support\Apps;

use App\Models\Parametre;
use Illuminate\Support\Facades\Schema;

/**
 * Les liens de téléchargement des deux applications Brio.
 *
 * Un lien vide n'est pas un lien : il ne doit jamais atteindre le gabarit public, sinon le
 * visiteur clique sur un bouton mort. Le filtrage vit ici, à la LECTURE, et pas seulement dans
 * l'écran d'administration : une valeur arrivée par une autre porte — import, tinker, console
 * mobile — ne doit pas pouvoir poser un `javascript:` dans un `href` public.
 */
final class LiensApplications
{
    public const CLIENT = 'client';

    public const PRESTATAIRE = 'prestataire';

    /** Les deux applications, dans l'ordre d'affichage. */
    public const APPLICATIONS = [self::CLIENT, self::PRESTATAIRE];

    /** Les trois destinations d'une application, dans l'ordre d'affichage. */
    public const DESTINATIONS = ['ios', 'android', 'smartlink'];

    /**
     * Le magasin de réglages est-il là ?
     *
     * LA PAGE D'ACCUEIL EST PUBLIQUE : elle ne peut pas mourir parce qu'une table manque. Le
     * bloc de téléchargement est une décoration de fin de page ; sans `parametres`, il ne
     * s'affiche pas, et le reste de la page vit.
     *
     * RIEN N'EST MEMORISE ICI, VOLONTAIREMENT. Un cache statique du cas positif rendait la
     * suite dépendante de l'ordre : un test migré le mettait à vrai, et le test suivant, servi
     * sans base par le même processus, tentait la requête et tombait. Une requête de plus par
     * rendu de la page d'accueil vaut mieux qu'une suite qui ment selon l'ordre.
     */
    private static function magasinDisponible(): bool
    {
        try {
            return Schema::hasTable('parametres');
        } catch (\Throwable $e) {
            // Pas de connexion du tout (page servie sans base) : on ne publie rien.
            return false;
        }
    }

    /** La clef de `parametres` qui porte une destination. */
    public static function cleLien(string $application, string $destination): string
    {
        return "apps_{$application}_{$destination}";
    }

    /** La clef de `parametres` qui décide si la carte d'une application s'affiche. */
    public static function cleVisibilite(string $application): string
    {
        return "apps_{$application}_visible";
    }

    /** La clef unique qui décide si les QR sont rendus sur écran large. */
    public static function cleQr(): string
    {
        return 'apps_qr_actif';
    }

    /**
     * Toutes les clefs du module, dans l'ordre où l'écran d'administration les présente.
     *
     * @return list<string>
     */
    public static function toutesLesCles(): array
    {
        $cles = [];

        foreach (self::APPLICATIONS as $application) {
            foreach (self::DESTINATIONS as $destination) {
                $cles[] = self::cleLien($application, $destination);
            }
        }

        foreach (self::APPLICATIONS as $application) {
            $cles[] = self::cleVisibilite($application);
        }

        $cles[] = self::cleQr();

        return $cles;
    }

    /**
     * Les liens publiables d'une application.
     *
     * Les destinations absentes du tableau retourné n'ont pas de lien exploitable : le gabarit
     * n'a rien à décider, il boucle sur ce qu'il reçoit.
     *
     * @return array{visible: bool, liens: array<string, string>}
     */
    public static function pour(string $application): array
    {
        if (! self::magasinDisponible()) {
            return ['visible' => false, 'liens' => []];
        }

        $liens = [];

        foreach (self::DESTINATIONS as $destination) {
            $brut = (string) Parametre::getValeur(self::cleLien($application, $destination), '');

            if (self::estPubliable($brut)) {
                $liens[$destination] = trim($brut);
            }
        }

        return [
            'visible' => self::estVisible($application) && $liens !== [],
            'liens' => $liens,
        ];
    }

    /** La carte d'une application est-elle allumée ? Par défaut oui — c'est l'absence de lien qui masque. */
    public static function estVisible(string $application): bool
    {
        return self::magasinDisponible()
            && (string) Parametre::getValeur(self::cleVisibilite($application), '1') === '1';
    }

    /** Les QR sont-ils rendus ? Par défaut oui : sur ordinateur, c'est le seul geste possible. */
    public static function qrActif(): bool
    {
        return self::magasinDisponible()
            && (string) Parametre::getValeur(self::cleQr(), '1') === '1';
    }

    /**
     * Le lien que l'on met derrière un QR : le lien unique s'il existe, sinon le premier magasin.
     *
     * Un QR qui pointe vers l'App Store est inutile à qui tient un Android ; le lien unique est
     * donc toujours préféré quand il est renseigné.
     */
    public static function lienPourQr(string $application): ?string
    {
        $liens = self::pour($application)['liens'];

        return $liens['smartlink'] ?? $liens['ios'] ?? $liens['android'] ?? null;
    }

    /**
     * Un lien est publiable s'il est une URL `https://` — rien d'autre.
     *
     * `http://` est refusé : un magasin d'applications n'a jamais d'URL en clair, et l'accepter
     * ouvrirait une redirection non chiffrée depuis la page d'accueil.
     */
    public static function estPubliable(string $valeur): bool
    {
        $valeur = trim($valeur);

        if ($valeur === '') {
            return false;
        }

        if (! str_starts_with(strtolower($valeur), 'https://')) {
            return false;
        }

        return filter_var($valeur, FILTER_VALIDATE_URL) !== false;
    }
}
