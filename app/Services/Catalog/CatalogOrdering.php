<?php

namespace App\Services\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Échanger deux voisins dans un ordre affiché.
 *
 * L'ORDRE N'EST PAS COSMÉTIQUE. Celui des secteurs est l'ordre du carrousel, celui des métiers
 * l'ordre du dock : le premier secteur est ce que voit tout visiteur, le premier métier ce qu'on
 * lui propose. Se tromper ici change ce que la plateforme met en avant.
 *
 * POURQUOI ON RENUMÉROTE TOUT AVANT D'ÉCHANGER. Les `sort_order` d'une table vivante finissent par
 * comporter des trous et des doublons — un import, une suppression, deux lignes créées à la même
 * seconde. Échanger deux valeurs égales ne déplacerait rien, et l'écran semblerait ignorer le clic.
 * On repose donc une suite 0..n-1 dans la même transaction, puis on échange des positions dont on
 * sait qu'elles sont distinctes.
 *
 * POURQUOI UN SERVICE. Les secteurs et les métiers ont exactement le même besoin, et le web le
 * résout déjà de cette façon. Deux copies auraient divergé, et l'ordre affiché par le mobile aurait
 * fini par différer de celui du client.
 */
class CatalogOrdering
{
    /**
     * Déplace un modèle d'un cran dans la liste ordonnée qu'on lui donne.
     *
     * Rend `false` si le mouvement n'a pas lieu — bord de liste, modèle absent. Ce n'est pas une
     * erreur : un bouton au bord ne doit ni casser ni surprendre.
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
