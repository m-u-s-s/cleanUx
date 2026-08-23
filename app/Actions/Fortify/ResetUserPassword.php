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
     * Valide et réinitialise le mot de passe oublié. ET COUPE TOUT LE RESTE.
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
