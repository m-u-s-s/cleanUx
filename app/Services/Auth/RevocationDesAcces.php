<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * CHANGER SON MOT DE PASSE COUPE LES AUTRES ACCÈS — sinon le geste ne sert à rien.
 *
 * Mesuré le 2026-08-16 : une réinitialisation complète par le vrai parcours web (302 vers /login)
 * laissait l'ancien jeton mobile rendre 200 sur `/auth/me` et `/client/bookings`, et le compteur de
 * jetons ne bougeait pas. Or `/auth/refresh` reconduit un jeton sans re-authentification : un jeton
 * volé survivait donc indéfiniment à la seule réaction que la victime peut avoir.
 *
 * TROIS PORTES, ET IL FAUT LES TROIS :
 *
 * 1. Les jetons Sanctum — l'application mobile. Sans eux, le téléphone du voleur reste connecté
 *    jusqu'à trente jours, renouvelables.
 * 2. Les sessions web enregistrées — l'autre navigateur. Ne vaut que pour le pilote `database` :
 *    avec `file` ou `redis` les lignes ne sont pas interrogeables par utilisateur, et on ne fait
 *    pas semblant de les supprimer. `SESSION_DRIVER=database` est le réglage de ce dépôt.
 * 3. Le `remember_token` — le cookie « se souvenir de moi », qui rouvre une session sans mot de
 *    passe et survivrait aux deux nettoyages précédents.
 *
 * CE QU'ON CONSERVE, ET POURQUOI. La personne qui change son mot de passe depuis son profil ne doit
 * pas se retrouver déconnectée du geste qu'elle vient de faire : son jeton ou sa session courante
 * s'excluent explicitement. Sur une réinitialisation par e-mail, il n'y a rien à conserver — le
 * navigateur qui pose le nouveau mot de passe n'est pas connecté.
 */
class RevocationDesAcces
{
    /**
     * @param  int|null  $jetonConserve  identifiant du jeton Sanctum courant, à laisser vivre
     * @param  string|null  $sessionConservee  identifiant de la session web courante, à laisser vivre
     */
    public function apresChangementDeMotDePasse(
        User $user,
        ?int $jetonConserve = null,
        ?string $sessionConservee = null,
    ): void {
        $this->revoquerLesJetons($user, $jetonConserve);
        $this->supprimerLesSessions($user, $sessionConservee);
        $this->renouvelerLeJetonDeMemorisation($user);
    }

    private function revoquerLesJetons(User $user, ?int $jetonConserve): void
    {
        $requete = $user->tokens();

        if ($jetonConserve !== null) {
            $requete->whereKeyNot($jetonConserve);
        }

        $requete->delete();
    }

    private function supprimerLesSessions(User $user, ?string $sessionConservee): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        // La table peut manquer sur une installation qui n'a jamais migré ce pilote : mieux vaut ne
        // rien supprimer que de faire échouer un changement de mot de passe.
        if (! Schema::hasTable($table)) {
            return;
        }

        $requete = DB::table($table)->where('user_id', $user->id);

        if ($sessionConservee !== null) {
            $requete->where('id', '!=', $sessionConservee);
        }

        $requete->delete();
    }

    /**
     * `forceFill` : `remember_token` n'est pas assignable en masse, et n'a pas à l'être — il ne
     * vient jamais d'un formulaire.
     */
    private function renouvelerLeJetonDeMemorisation(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
