<?php

namespace App\Support\Navigation;

use App\Enums\OrganizationType;
use App\Models\OrganizationAccount;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * L'ESPACE DE LA ROUTE COURANTE.
 *
 * Un compte rattache a une societe n'a qu'un espace : le sien. Ses ecrans personnels sont
 * re-declares sous le prefixe de la societe (`.../moi/...`), et c'est le NOM DE LA ROUTE —
 * jamais le role de l'utilisateur — qui dit quelle barre porter.
 */
class EspaceCourant
{
    /** @var array<string, string> espace societe => espace personnel qu'il accueille */
    public const FUSIONS = [
        'client-company' => 'client',
        'provider-company' => 'employe',
    ];

    /** L'accueil et le repertoire restent ceux de la societe : deux d'un meme espace. */
    public const REPRIS_PAR_LA_SOCIETE = ['dashboard', 'modules'];

    /** L'espace societe a porter, ou null pour la barre personnelle. */
    public static function societe(): ?string
    {
        $nom = (string) (Route::currentRouteName() ?? '');

        foreach (array_keys(self::FUSIONS) as $espace) {
            if (str_starts_with($nom, $espace.'.')) {
                return $espace;
            }
        }

        if (str_starts_with($nom, 'admin.') || str_starts_with($nom, 'super-admin.')) {
            return null;
        }

        // LES ROUTES PARTAGEES — location de vehicules, offre premium, presence — n'appartiennent
        // a aucun espace. Sans ce repli, elles ramenaient la barre personnelle et, avec elle,
        // le sentiment de deux espaces. Bornees au tableau de bord : la vitrine a sa propre barre.
        if (! request()->is('dashboard/*')) {
            return null;
        }

        return self::espaceDuCompte(Auth::user());
    }

    /** L'espace societe d'un compte, ou null s'il n'appartient a aucune organisation active. */
    public static function espaceDuCompte(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        // Une organisation HYBRIDE est cliente ET prestataire : c'est le role du compte qui
        // tranche, sans quoi un salarie de terrain heriterait de la barre cliente.
        if ($user->isEmploye() && self::organisationEstDeType($user, 'provider')) {
            return 'provider-company';
        }

        if (self::organisationEstDeType($user, 'client')) {
            return 'client-company';
        }

        return self::organisationEstDeType($user, 'provider') ? 'provider-company' : null;
    }

    /**
     * LA CONDITION DE `org.type`, SANS LE REFUS.
     *
     * `RedirigeVersLEspaceFusionne` renvoie vers des routes gardees par ce middleware : deux
     * lectures divergentes enverraient l'utilisateur droit sur un 403, sans retour possible.
     */
    public static function organisationEstDeType(?User $user, string $attendu): bool
    {
        if ($user === null) {
            return false;
        }

        // L'organisation active vit dans deux colonnes plus un repli : `organizationContextId()`
        // est la resolution unique du depot.
        $orgId = $user->organizationContextId();

        if (empty($orgId)) {
            return false;
        }

        // `organization_accounts.type` est une colonne string non castee.
        $rawType = OrganizationAccount::query()->whereKey($orgId)->value('type');
        $type = OrganizationType::tryFrom((string) $rawType);

        return match ($attendu) {
            'client' => (bool) $type?->isClient(),
            'provider' => (bool) $type?->isProvider(),
            default => false,
        };
    }

    /** La route du repertoire de modules de l'espace. */
    public static function routeDesModules(string $espace): string
    {
        return $espace.'.modules';
    }

    /** La route d'accueil de l'espace. */
    public static function routeDAccueil(string $espace): string
    {
        return $espace.'.dashboard';
    }
}
