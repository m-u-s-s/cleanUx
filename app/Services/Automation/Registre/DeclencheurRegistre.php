<?php

namespace App\Services\Automation\Registre;

use App\Services\Automation\Contracts\Declencheur;

/** Cle => declencheur. Le vocabulaire des evenements, en code. */
class DeclencheurRegistre
{
    /** @var array<string, Declencheur> */
    protected array $declencheurs = [];

    public function enregistrer(Declencheur $declencheur): void
    {
        $this->declencheurs[$declencheur->cle()] = $declencheur;
    }

    public function trouver(string $cle): ?Declencheur
    {
        return $this->declencheurs[$cle] ?? null;
    }

    /** @return array<string, Declencheur> */
    public function toutes(): array
    {
        return $this->declencheurs;
    }

    /** @return list<Declencheur> */
    public function pourEvenement(object $evenement): array
    {
        return array_values(array_filter($this->declencheurs, function (Declencheur $d) use ($evenement): bool {
            // La classe d'abord, la discrimination ensuite : `sApplique` n'a pas a se defendre
            // contre un evenement d'un autre type.
            $classe = $d->evenement();

            return $evenement instanceof $classe && $d->sApplique($evenement);
        }));
    }
}
