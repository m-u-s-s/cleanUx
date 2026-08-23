<?php

namespace App\Models\Concerns;

use App\Models\CatalogTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/** Traduire les libellés du catalogue, sans jamais laisser un écran vide. */
trait HasCatalogTranslations
{
    /** @return MorphMany<CatalogTranslation, $this> */
    public function translations(): MorphMany
    {
        return $this->morphMany(CatalogTranslation::class, 'translatable');
    }

    /** Le libellé dans la langue demandée, ou le meilleur repli disponible. */
    public function translate(string $field, ?string $locale = null): ?string
    {
        // UN OBJET QUI N'EXISTE PAS EN BASE N'A PAS DE TRADUCTION, ET NE DOIT RIEN COÛTER.
        if (! $this->exists) {
            return $this->getAttribute($field);
        }

        $locale ??= App::getLocale();
        $fallback = (string) Config::get('i18n.default', 'fr');

        $available = $this->relationLoaded('translations')
            ? $this->translations
            : $this->translations()->where('field', $field)->get();

        $byLocale = $available
            ->where('field', $field)
            ->filter(fn (CatalogTranslation $t) => filled($t->value))
            ->keyBy('locale');

        // `has()` puis `get()` plutôt que `?->` : la collection est indexée par langue et `get()` y est typé non-nul, si bien que l'accès sûr n'y protégeait rien — il masquait seulement l'absence, que ce `has()` dit maintenant explicitement.
        $traduit = static fn (string $code): ?string => $byLocale->has($code)
            ? (string) $byLocale->get($code)->value
            : null;

        return $traduit($locale)
            ?? $traduit($fallback)
            ?? $this->getAttribute($field);
    }

    /** Enregistre — ou retire — une traduction. */
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
     * @param  list<string>  $fields
     * @return list<string>
     */
    public function missingLocales(array $fields = ['label']): array
    {
        // Le cast dit ce que la configuration peut réellement rendre : `Config::get()` n'a aucun
        // type, et une collection construite sur `mixed` ne sait pas ce qu'elle contient.
        $enabled = collect((array) Config::get('i18n.locales', []))
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
