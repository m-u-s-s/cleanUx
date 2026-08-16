<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\Auth\RevocationDesAcces;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    public function __construct(private readonly RevocationDesAcces $revocation) {}

    /**
     * Valide et met à jour le mot de passe depuis le profil.
     *
     * ET COUPE LES AUTRES ACCÈS : jetons mobiles, sessions web enregistrées, cookie « se souvenir de
     * moi ». Un changement de mot de passe qui laisse les autres appareils connectés n'est pas un
     * changement de mot de passe, c'est une formalité.
     *
     * La session COURANTE est épargnée : se faire déconnecter du geste qu'on vient de faire ferait
     * croire à un échec, et renvoyer sur l'écran de connexion juste après avoir choisi un mot de
     * passe fait douter de celui qu'on vient de taper.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('Le mot de passe actuel est incorrect.'),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();

        $this->revocation->apresChangementDeMotDePasse(
            $user,
            sessionConservee: request()->hasSession() ? request()->session()->getId() : null,
        );
    }
}
