<?php

namespace App\Services\Conditions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Ce qu'une entite doit savoir dire pour etre filtrable par un arbre de conditions. */
interface EntityDescriptor
{
    /** @return Builder<Model> */
    public function baseQuery(): Builder;

    /** @return array<string, FieldBinding> les cles SONT la liste blanche des champs */
    public function fields(): array;

    /** @return list<string> les operateurs permis pour cette entite */
    public function operators(): array;
}
