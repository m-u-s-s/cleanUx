<?php

namespace App\Services\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Échanger deux voisins dans un ordre affiché. L'ORDRE N'EST PAS COSMÉTIQUE. */
class CatalogOrdering
{
    /**
     * Déplace un modèle d'un cran dans la liste ordonnée qu'on lui donne.
     *
     * @param  Builder<covariant Model>  $liste  Le périmètre du classement (un secteur, tous les secteurs).
     */
    public function deplacer(Builder $liste, int $id, int $sens): bool
    {
        $ordonnes = $liste->orderBy('sort_order')->orderBy('id')->get()->values();

        $index = $ordonnes->search(fn (Model $m) => (int) $m->getKey() === $id);

        if ($index === false) {
            return false;
        }

        $cible = $index + $sens;

        if ($cible < 0 || $cible >= $ordonnes->count()) {
            return false;
        }

        DB::transaction(function () use ($ordonnes, $index, $cible) {
            // Une suite propre d'abord : sans cela, deux `sort_order` égaux rendraient l'échange
            // sans effet et l'écran paraîtrait sourd au clic.
            $ordonnes->each(fn (Model $m, int $i) => $m->forceFill(['sort_order' => $i])->save());

            $ordonnes[$index]->forceFill(['sort_order' => $cible])->save();
            $ordonnes[$cible]->forceFill(['sort_order' => $index])->save();
        });

        return true;
    }
}
