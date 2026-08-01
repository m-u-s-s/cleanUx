<?php

namespace App\Models\Concerns;

use App\Models\CatalogTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * Traduire les libellés du catalogue, sans jamais laisser un écran vide.
 *
 * LA CHAÎNE DE REPLI EST LA RÈGLE : langue demandée → langue par défaut → colonne de base. Un
 * client néerlandophone devant une question sans traduction voit le français ; il ne voit jamais
 * un blanc. Une question muette est pire qu'une question dans la mauvaise langue — elle ne peut
 * même pas être devinée.
 *
 * Écrire une traduction vide EFFACE la ligne plutôt que d'enregistrer une chaîne creuse : sans
 * cela, vider le champ dans l'écran d'administration produirait un libellé blanc en production,
 * alors que l'intention était manifestement de revenir au libellé de base.
 */
trait HasCatalogTranslations
{
    /** @return MorphMany<CatalogTranslation, $this> */
    public function translations(): MorphMany
    {
        return $this->morphMany(CatalogTranslation::class, 'translatable');
    }

    /**
     * Le libellé dans la langue demandée, ou le meilleur repli disponible.
     *
     * Jamais nul quand la colonne de base est renseignée : c'est ce qui garantit qu'aucun écran
     * n'affiche de vide.
     */
    public function translate(string $field, ?string $locale = null): ?string
    {
        $locale ??= App::getLocale();
        $fallback = (string) Config::get('i18n.default', 'fr');

        $available = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->where('field', $field)->get();

        $byLocale = $available
            ->where('field', $field)
            ->filter(fn (CatalogTranslation $t) => filled($t->value))
            ->keyBy('locale');

        return $byLocale->get($locale)?->value
            ?? $byLocale->get($fallback)?->value
            ?? $this->getAttribute($field);
    }

    /**
     * Enregistre — ou retire — une traduction.
     *
     * Une valeur vide supprime la ligne : revenir au libellé de base doit être aussi simple que
     * d'effacer le champ, et ne doit surtout pas produire un écran blanc.
     */
    public function setTranslation(string $field, string $locale, ?string $value): void
    {
        if (blank($value)) {
            $this->translations()->where('field', $field)->where('locale', $locale)->delete();
            $this->unsetRelation('translations');

            return;
        }

        $this->translations()->updateOrCreate(
            ['field' => $field, 'locale' => $locale],
            ['value' => $value],
        );

        $this->unsetRelation('translations');
    }

    /**
     * Les langues où ce libellé manque encore.
     *
     * Sert à l'écran d'administration : dire « il manque le néerlandais » vaut mieux que de
     * laisser découvrir le trou en production, par un client qui ne comprend pas la question.
     *
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function missingLocales(array $fields = ['label']): array
    {
        $enabled = collect(Config::get('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->keys()
            ->reject(fn ($locale) => $locale === (string) Config::get('i18n.default', 'fr'));

        $present = $this->translations()->get();

        return $enabled
            ->reject(function (string $locale) use ($fields, $present) {
                foreach ($fields as $field) {
                    // Un seul champ traduit ne suffit pas : la question doit être lisible en entier.
                    $match = $present->first(
                        fn (CatalogTranslation $t) => $t->locale === $locale
                            && $t->field === $field
                            && filled($t->value),
                    );

                    if (! $match && filled($this->getAttribute($field))) {
                        return false;
                    }
                }

                return true;
            })
            ->values()
            ->all();
    }
}
