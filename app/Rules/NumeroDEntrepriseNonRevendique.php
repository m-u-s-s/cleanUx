<?php

namespace App\Rules;

use App\Models\OrganizationAccount;
use App\Support\Validation\BusinessNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** UN NUMÉRO D'ENTREPRISE DÉSIGNE UNE SEULE SOCIÉTÉ — et il est PUBLIC. */
class NumeroDEntrepriseNonRevendique implements ValidationRule
{
    public function __construct(private readonly string $typeDOrganisation) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        $normalise = BusinessNumber::normalise($value);

        if ($normalise === '') {
            // Un numéro illisible est refusé par `ValidBusinessNumber`, pas ici : deux règles qui
            // disent la même chose donneraient deux messages pour une seule faute.
            return;
        }

        $dejaPris = OrganizationAccount::query()
            ->where('type', $this->typeDOrganisation)
            ->get(['tva_number'])
            ->contains(fn (OrganizationAccount $org): bool => BusinessNumber::normalise((string) $org->tva_number) === $normalise);

        if ($dejaPris) {
            $fail("Ce numéro d'entreprise est déjà rattaché à une société sur la plateforme. Si c'est la vôtre, demandez à en devenir membre plutôt que d'en créer une seconde.");
        }
    }
}
