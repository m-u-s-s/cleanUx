<?php

namespace App\Services\I18n;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Decorator du Loader translation Laravel : merge les overrides DB
 * par-dessus les fichiers PHP/JSON.
 *
 * Priorité : DB override > fichier disque > fallback locale.
 *
 * Cache 5 min (configurable via i18n.overrides.cache_ttl_seconds) pour ne pas
 * frapper la DB à chaque __() / trans(). Cache invalidé manuellement par
 * `TranslationOverride::saved` event ou Admin UI.
 */
class TranslationOverrideLoader implements Loader
{
    public function __construct(protected Loader $inner)
    {
    }

    public function load($locale, $group, $namespace = null)
    {
        $base = $this->inner->load($locale, $group, $namespace);

        if (! Config::get('i18n.overrides.enabled', true)) {
            return $base;
        }

        try {
            if (! Schema::hasTable('translation_overrides')) {
                return $base;
            }
        } catch (\Throwable $e) {
            return $base;
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

        return Cache::remember($cacheKey, $ttl, function () use ($locale, $group, $namespace) {
            return $this->fetchOverrides($locale, $group, $namespace);
        });
    }

    /**
     * @return array<string,string>
     */
    protected function fetchOverrides(string $locale, string $group, string $namespace): array
    {
        try {
            return \App\Models\TranslationOverride::query()
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
        // Pas d'iterator de keys universel → on flush par patterns connus
        if ($locale && $group) {
            Cache::forget("i18n:overrides:*:{$locale}:{$group}");
            return;
        }

        if ($locale) {
            foreach (['app', 'ui', 'messages', 'validation', 'auth', '*'] as $g) {
                Cache::forget("i18n:overrides:*:{$locale}:{$g}");
            }
            return;
        }

        // Worst case : flush par config locales × groupes connus
        $locales = (array) Config::get('i18n.locales', []);
        foreach (array_keys($locales) as $loc) {
            foreach (['app', 'ui', 'messages', 'validation', 'auth', '*'] as $g) {
                Cache::forget("i18n:overrides:*:{$loc}:{$g}");
            }
        }
    }
}
