<?php

namespace App\Support\Brand;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * QUELLE MARQUE POUR QUI — la seule réponse, pour toutes les surfaces web.
 *
 * La plateforme a DEUX marques, pas une : « Brio Client » et « Brio Provider ». Chacune existe en
 * version claire et sombre. Quatre images, donc, et un choix à faire sur chaque page — barre de
 * navigation, écran de connexion, onglet du navigateur, tuile d'installation.
 *
 * CE CHOIX SE DÉCIDE ICI ET NULLE PART AILLEURS. Recopié dans chaque gabarit, il aurait dérivé au
 * premier espace ajouté : c'est exactement ce qui était arrivé au logo précédent, présent en quatre
 * variantes divergentes — le SVG mauve de Jetstream sur les pages d'authentification, une pastille
 * « Br » dans la barre authentifiée, une pastille « Cx » sur le site public, et « Brio Pro » en
 * texte dans l'espace société. Personne ne savait laquelle faisait foi.
 *
 * LE CAS DE L'ADMINISTRATION EST UN CHOIX, pas un oubli : aucune des deux marques ne dit « admin ».
 * La console est rangée du côté PROVIDER parce qu'elle sert l'exploitation, aux côtés des
 * prestataires. Le jour où une marque « admin » existera, c'est cette ligne qu'il faudra changer.
 *
 * CLAIR/SOMBRE N'EST PAS RÉSOLU ICI. Les deux variantes sont TOUJOURS rendues, et c'est le CSS qui
 * en montre une : le thème se choisit dans le navigateur, après le rendu du serveur, et un choix
 * fait en PHP servirait une image sombre à qui vient de basculer en clair — avec, en prime, un cache
 * HTTP qui figerait l'erreur pour tout le monde.
 */
final class BrandMark
{
    public const CLIENT = 'client';

    public const PROVIDER = 'provider';

    /** @var list<string> */
    public const SPACES = [self::CLIENT, self::PROVIDER];

    /**
     * LES TAILLES RÉELLEMENT PRODUITES sur le disque.
     *
     * Elles sont déclarées ici parce qu'un composant qui calcule sa taille — « le double de
     * l'affichage, pour les écrans à haute densité » — tombe sinon sur des fichiers qui n'existent
     * pas : 40 px demandés donnaient `-80.png`, et le navigateur affichait un cadre vide. Une image
     * absente ne casse rien et ne se voit qu'à l'œil : exactement le défaut qu'une suite de tests
     * ne relève jamais.
     *
     * @var list<int>
     */
    public const SIZES = [32, 48, 64, 96, 180, 192, 256, 512];

    /**
     * L'espace de marque d'un utilisateur — ou du visiteur, faute d'utilisateur.
     *
     * LE VISITEUR EST UN CLIENT. Le site public vend un service à des particuliers et des
     * entreprises clientes ; l'accueil, les pages légales et le formulaire d'inscription
     * s'adressent d'abord à eux. Un prestataire qui vient s'inscrire verra la marque prestataire
     * dès qu'il aura choisi son espace, pas avant.
     */
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

    /**
     * La taille disponible la plus proche PAR EXCÈS — jamais en deçà.
     *
     * Rendre une image plus petite que sa boîte la rend floue ; la rendre plus grande ne coûte que
     * des octets. Au-delà de la plus grande déclinaison, on sert l'originale.
     */
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

    /**
     * La couleur de fond de la marque — pour `theme-color` et la tuile d'installation.
     *
     * Elle vient de l'œuvre elle-même : un `theme-color` bleu générique au-dessus d'une icône
     * crème ou anthracite produisait une barre système d'une autre marque que l'application.
     */
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
