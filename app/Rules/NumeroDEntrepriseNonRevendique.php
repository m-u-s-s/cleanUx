<?php

namespace App\Rules;

use App\Models\OrganizationAccount;
use App\Support\Validation\BusinessNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * UN NUMÉRO D'ENTREPRISE DÉSIGNE UNE SEULE SOCIÉTÉ — et il est PUBLIC.
 *
 * Mesuré le 2026-08-16 : deux sociétés prestataires distinctes se sont inscrites avec le même
 * `BE0123456749`, sans un mot. Ces numéros sont publics — ils figurent sur chaque facture et dans
 * les registres officiels. N'importe qui pouvait donc inscrire une société au nom d'une autre, et
 * la vérification d'entreprise (KYB) l'aurait déclarée conforme : elle contrôle que le NUMÉRO
 * existe et à qui il appartient dans les registres, pas que la personne qui le saisit possède
 * l'entreprise. Le dossier ressortait « société vérifiée » avec l'identité de quelqu'un d'autre.
 *
 * LA PORTÉE EST LE TYPE, PAS LA PLATEFORME ENTIÈRE. Une même entreprise peut légitimement être
 * cliente ET prestataire chez nous — une société de nettoyage qui commande aussi du jardinage. Elle
 * a alors deux organisations, une par casquette, et c'est voulu. Ce qui n'existe pas, c'est deux
 * sociétés PRESTATAIRES pour un seul numéro : ce serait la même entreprise deux fois, avec deux
 * comptes de paiement, deux dossiers KYB et un doute permanent sur celui qui encaisse.
 *
 * La comparaison se fait sur la forme NORMALISÉE : `BE 0123.456.749` et `be0123456749` sont le même
 * numéro, et une règle qui comparerait les chaînes brutes se contournerait avec un point.
 */
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
