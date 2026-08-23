<?php

namespace App\Models\Contracts;

use App\Models\CatalogTranslation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Un objet du catalogue dont les libellés se traduisent.
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
