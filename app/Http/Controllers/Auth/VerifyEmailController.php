<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CONFIRMER SON ADRESSE D'UN SEUL GESTE.
 *
 * Celui de Fortify exige `auth:web` : le lien reçu sur un téléphone renvoyait vers `/login`, et il
 * fallait retaper son mot de passe sur un site pour confirmer une adresse. C'est un entonnoir percé,
 * et depuis que `verified` garde l'API, c'était le seul chemin hors du mur.
 *
 * L'AUTHENTIFICATION N'AJOUTAIT RIEN : l'URL signée EST la preuve. La signature atteste qu'elle
 * vient de nous, l'expiration la borne, et l'empreinte la lie à l'adresse du jour. Qui la détient
 * a reçu l'e-mail — exactement ce qu'on cherche à établir.
 *
 * CE QU'ELLE N'OUVRE PAS : aucune session. Confirmer n'est pas se connecter.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): Response|RedirectResponse
    {
        /*
         * La signature se vérifie ICI plutôt que par le middleware `signed` : un lien de plus de
         * soixante minutes est le cas COURANT, et il mérite une page qui le dit — pas un « 403
         * Forbidden » qui laisse croire à une faute.
         */
        if (! $request->hasValidSignature()) {
            return response()->view('auth.verify-email-expired', [], 403);
        }

        $user = User::find($id);

        // L'empreinte lie le lien à l'adresse du moment : en changer périme les liens déjà envoyés.
        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->view('auth.verify-email-expired', [], 403);
        }

        // Rouvrir un lien déjà consommé n'est pas une erreur : on le dit, sans réémettre l'événement.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        // Un visiteur déjà connecté SUR CE NAVIGATEUR retrouve son tableau de bord. Les autres —
        // le cas mobile, qui est la raison de ce contrôleur — voient une page qui referme la boucle.
        if ($request->user()?->is($user)) {
            return redirect()->intended(config('fortify.home', '/dashboard').'?verified=1');
        }

        return response()->view('auth.verify-email-done');
    }
}
