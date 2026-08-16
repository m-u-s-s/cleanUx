<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Auth\RevocationDesAcces;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly RevocationDesAcces $revocation) {}

    /**
     * Valide et réinitialise le mot de passe oublié.
     *
     * ET COUPE TOUT LE RESTE. Mesuré le 2026-08-16 : après une réinitialisation complète, l'ancien
     * jeton mobile rendait toujours 200 et le compteur de jetons ne bougeait pas — un jeton volé
     * survivait donc à la seule réaction que la victime peut avoir, d'autant que `/auth/refresh` le
     * reconduit sans re-authentification.
     *
     * Rien n'est conservé sur ce chemin : le navigateur qui pose le nouveau mot de passe n'est pas
     * connecté, il n'y a donc pas de session courante à épargner.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $this->revocation->apresChangementDeMotDePasse($user);
    }
}
