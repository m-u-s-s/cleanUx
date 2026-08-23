<?php

namespace App\Support\Brand;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** QUELLE MARQUE POUR QUI — la seule réponse, pour toutes les surfaces web. */
final class BrandMark
{
    public const CLIENT = 'client';

    public const PROVIDER = 'provider';

    /** @var list<string> */
    public const SPACES = [self::CLIENT, self::PROVIDER];

    /**
     * LES TAILLES RÉELLEMENT PRODUITES sur le disque.
     *
     * @var list<int>
     */
    public const SIZES = [32, 48, 64, 96, 180, 192, 256, 512];

    /** L'espace de marque d'un utilisateur — ou du visiteur, faute d'utilisateur. */
    public static function spaceFor(?User $user = null): string
    {
        $user ??= Auth::user();

        if (! $user) {
            return self::CLIENT;
        }

        // Un compte de société PRESTATAIRE porte la marque prestataire, quel que soit son rôle
        // dans l'organisation : le gérant, le répartiteur et le nettoyeur sont du même côté.
        if ($user->isProviderCompanyWorker()) {
            return self::PROVIDER;
        }

        return match ($user->role) {
            User::ROLE_EMPLOYE => self::PROVIDER,
            // Voir l'en-tête : rangée du côté exploitation, faute de marque qui lui soit propre.
            User::ROLE_ADMIN => self::PROVIDER,
            default => self::CLIENT,
        };
    }

    /** La taille disponible la plus proche PAR EXCÈS — jamais en deçà. */
    public static function nearestSize(int $wanted): ?int
    {
        foreach (self::SIZES as $taille) {
            if ($taille >= $wanted) {
                return $taille;
            }
        }

        return null;
    }

    /** Le chemin public d'une variante, éventuellement à une taille précise. */
    public static function path(string $space, string $theme, ?int $size = null): string
    {
        $space = in_array($space, self::SPACES, true) ? $space : self::CLIENT;
        $theme = $theme === 'dark' ? 'dark' : 'light';
        $suffixe = $size === null ? '' : "-{$size}";

        return "/images/brand/brio-{$space}-{$theme}{$suffixe}.png";
    }

    /** La couleur de fond de la marque — pour `theme-color` et la tuile d'installation. */
    public static function themeColor(string $space, string $theme): string
    {
        return $theme === 'dark' ? '#191c20' : '#eeeae0';
    }

    /** Le libellé lisible de la marque, pour les textes alternatifs. */
    public static function label(string $space): string
    {
        return $space === self::PROVIDER ? 'Brio Provider' : 'Brio Client';
    }
}
