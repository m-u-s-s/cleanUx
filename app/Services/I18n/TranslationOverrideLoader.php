<?php

namespace App\Services\I18n;

use App\Models\TranslationOverride;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/** Decorator du Loader translation Laravel : merge les overrides DB par-dessus les fichiers PHP/JSON. */
class TranslationOverrideLoader implements Loader
{
    /** Retenu une fois la table trouvee : `Schema::hasTable()` coute une requete a chaque appel. */
    protected bool $tablePresente = false;

    public function __construct(protected Loader $inner) {}

    public function load($locale, $group, $namespace = null)
    {
        $base = $this->inner->load($locale, $group, $namespace);

        if (! Config::get('i18n.overrides.enabled', true)) {
            return $base;
        }

        // On ne memorise que le cas POSITIF : une table creee entre deux appels — une migration
        // en cours de test — doit rester detectable.
        if (! $this->tablePresente) {
            try {
                if (! Schema::hasTable('translation_overrides')) {
                    return $base;
                }
            } catch (\Throwable $e) {
                return $base;
            }

            $this->tablePresente = true;
        }

        $overrides = $this->fetchOverridesCached($locale, $group, $namespace ?? '*');

        if (empty($overrides)) {
            return $base;
        }

        $merged = is_array($base) ? $base : [];
        foreach ($overrides as $key => $value) {
            if (str_contains($key, '.')) {
                data_set($merged, $key, $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->inner->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->inner->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->inner->namespaces();
    }

    public function addGlobalNamespace($namespace = null)
    {
        if (method_exists($this->inner, 'addGlobalNamespace')) {
            return $this->inner->addGlobalNamespace($namespace);
        }
    }

    /**
     * @return array<string,string>
     */
    protected function fetchOverridesCached(string $locale, string $group, string $namespace): array
    {
        $ttl = (int) Config::get('i18n.overrides.cache_ttl_seconds', 300);
        $cacheKey = "i18n:overrides:{$namespace}:{$locale}:{$group}";

        if ($ttl <= 0) {
            return $this->fetchOverrides($locale, $group, $namespace);
        }

        // Une entree par groupe existait deja : on la garde, elle est purgee groupe par groupe.
        if (Cache::has($cacheKey)) {
            return (array) Cache::get($cacheKey, []);
        }

        // Sinon on lit la locale ENTIERE d'un coup. Une page qui touche cinq groupes faisait
        // cinq requetes ; elle en fait une.
        $parGroupe = $this->fetchLocaleCached($locale, $ttl);
        $valeurs = $parGroupe[$namespace][$group] ?? [];

        Cache::put($cacheKey, $valeurs, $ttl);

        return $valeurs;
    }

    /**
     * Tous les overrides publies d'une locale, indexes par namespace puis par groupe.
     *
     * @return array<string, array<string, array<string,string>>>
     */
    protected function fetchLocaleCached(string $locale, int $ttl): array
    {
        return (array) Cache::remember("i18n:overrides:locale:{$locale}", $ttl, function () use ($locale) {
            try {
                $parGroupe = [];

                TranslationOverride::query()
                    ->published()
                    ->forLocale($locale)
                    ->get(['namespace', 'group', 'key', 'value'])
                    ->each(function ($ligne) use (&$parGroupe) {
                        $parGroupe[$ligne->namespace][$ligne->group][$ligne->key] = $ligne->value;
                    });

                return $parGroupe;
            } catch (\Throwable $e) {
                report($e);

                return [];
            }
        });
    }

    /**
     * @return array<string,string>
     */
    protected function fetchOverrides(string $locale, string $group, string $namespace): array
    {
        try {
            return TranslationOverride::query()
                ->published()
                ->forLocale($locale)
                ->where('group', $group)
                ->where('namespace', $namespace)
                ->pluck('value', 'key')
                ->all();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    public static function flushCache(?string $locale = null, ?string $group = null): void
    {
        // Pas d'iterator de keys universel → on flush par patterns connus.
        // L'entree PAR LOCALE part aussi : sans elle, un groupe purge se rechargerait
        // depuis un instantane perime, et la correction resterait invisible 300 s.
        if ($locale && $group) {
            Cache::forget("i18n:overrides:*:{$locale}:{$group}");
            Cache::forget("i18n:overrides:locale:{$locale}");

            return;
        }

        if ($locale) {
            foreach (['app', 'ui', 'messages', 'validation', 'auth', '*'] as $g) {
                Cache::forget("i18n:overrides:*:{$locale}:{$g}");
            }

            Cache::forget("i18n:overrides:locale:{$locale}");

            return;
        }

        // Worst case : flush par config locales × groupes connus
        $locales = (array) Config::get('i18n.locales', []);
        foreach (array_keys($locales) as $loc) {
            foreach (['app', 'ui', 'messages', 'validation', 'auth', '*'] as $g) {
                Cache::forget("i18n:overrides:*:{$loc}:{$g}");
            }

            Cache::forget("i18n:overrides:locale:{$loc}");
        }
    }
}
