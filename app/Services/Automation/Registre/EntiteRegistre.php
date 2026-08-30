<?php

namespace App\Services\Automation\Registre;

use App\Services\Conditions\EntityDescriptor;

/** Cle => descripteur d'entite. Une instance NEUVE a chaque resolution. */
class EntiteRegistre
{
    /** @var array<string, class-string<EntityDescriptor>> */
    protected array $entites = [];

    /** @param class-string<EntityDescriptor> $classe */
    public function enregistrer(string $cle, string $classe): void
    {
        $this->entites[$cle] = $classe;
    }

    public function descripteur(string $cle): ?EntityDescriptor
    {
        if (! isset($this->entites[$cle])) {
            return null;
        }

        return app($this->entites[$cle]);
    }

    /** @return list<string> */
    public function cles(): array
    {
        return array_keys($this->entites);
    }
}
