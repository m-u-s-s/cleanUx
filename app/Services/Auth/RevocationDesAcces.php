<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** CHANGER SON MOT DE PASSE COUPE LES AUTRES ACCÈS — sinon le geste ne sert à rien. */
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

    /** `forceFill` : `remember_token` n'est pas assignable en masse, et n'a pas à l'être — il ne vient jamais d'un formulaire. */
    private function renouvelerLeJetonDeMemorisation(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }
}
