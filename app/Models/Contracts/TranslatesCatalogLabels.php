<?php

namespace App\Models\Contracts;

use App\Models\CatalogTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Un objet du catalogue dont les libellés se traduisent.
 *
 * L'interface existe pour que le TYPE dise ce qui est réellement exigé. Un trait ne peut pas servir
 * de type : accepter un `Model` quelconque laissait passer, à l'analyse comme à la relecture, un
 * appel qui plante à l'exécution sur un modèle sans `setTranslation()`.
 *
 * @property-read Collection<int, CatalogTranslation> $translations
 */
interface TranslatesCatalogLabels
{
    /** @return MorphMany<CatalogTranslation, $this> */
    public function translations(): MorphMany;

    /** Le libellé dans la langue demandée, ou le meilleur repli — jamais un vide. */
    public function translate(string $field, ?string $locale = null): ?string;

    /** Écrit la traduction, ou la retire si la valeur est vide. */
    public function setTranslation(string $field, string $locale, ?string $value): void;

    /**
     * Les langues où ce libellé manque encore.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function missingLocales(array $fields = ['label']): array;
}
