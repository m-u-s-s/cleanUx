<?php

namespace App\Rules;

use App\Support\Validation\BusinessNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidBusinessNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! BusinessNumber::isValid($value)) {
            $fail("Ce numéro d'entreprise est invalide. Vérifiez votre numéro BCE (10 chiffres), SIRET (14) ou TVA (BE0123456749, FR12345678901).");
        }
    }
}
