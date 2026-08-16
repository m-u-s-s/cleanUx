<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
            /*
             * Le second facteur, quand le compte en porte un. Optionnels ici : le serveur répond
             * `two_factor_required` au premier appel, l'application affiche alors le champ et
             * renvoie la même requête avec le code. Exiger le code d'emblée obligerait à savoir,
             * avant de parler au serveur, si ce compte a une 2FA — c'est-à-dire à le divulguer.
             */
            'two_factor_code' => ['nullable', 'string', 'max:12'],
            'recovery_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
