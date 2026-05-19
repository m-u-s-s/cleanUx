<?php

namespace App\Services\I18n;

use Illuminate\Support\Facades\Config;

/**
 * Scanne les fichiers de langue et détecte les clés manquantes par locale.
 *
 * - Construit l'ensemble union de toutes les clés (depuis fr/en/nl/es/it/de…)
 * - Identifie pour chaque locale les clés présentes ailleurs mais absentes ici
 * - Identifie aussi les clés qui ont la même valeur que la fallback (traduction
 *   manquante = string copiée d'EN par exemple)
 */
class TranslationScanner
{
    public function __construct(protected LocaleResolver $resolver)
    {
    }

    /**
     * @return array<string, array{total:int, missing:array<int,string>, untranslated:array<int,string>}>
     */
    public function scanAllLocales(): array
    {
        $allLocales = $this->resolver->supportedCodes();
        $fallback = $this->resolver->fallback();

        $allByLocale = [];
        foreach ($allLocales as $locale) {
            $allByLocale[$locale] = $this->flattenLocale($locale);
        }

        $unionKeys = [];
        foreach ($allByLocale as $entries) {
            foreach (array_keys($entries) as $key) {
                $unionKeys[$key] = true;
            }
        }
        $unionKeys = array_keys($unionKeys);

        $fallbackEntries = $allByLocale[$fallback] ?? [];

        $report = [];
        foreach ($allLocales as $locale) {
            $entries = $allByLocale[$locale] ?? [];
            $missing = [];
            $untranslated = [];

            foreach ($unionKeys as $key) {
                if (! array_key_exists($key, $entries)) {
                    $missing[] = $key;
                    continue;
                }
                if ($locale !== $fallback
                    && isset($fallbackEntries[$key])
                    && $fallbackEntries[$key] === $entries[$key]
                    && trim((string) $entries[$key]) !== '') {
                    $untranslated[] = $key;
                }
            }

            $report[$locale] = [
                'total' => count($entries),
                'missing' => $missing,
                'untranslated' => $untranslated,
            ];
        }

        return $report;
    }

    /**
     * @return array<string,string> ['group.nested.key' => 'value']
     */
    public function flattenLocale(string $locale): array
    {
        $flat = [];
        $langPath = base_path("lang/{$locale}");

        if (is_dir($langPath)) {
            foreach (glob($langPath . '/*.php') as $file) {
                $group = pathinfo($file, PATHINFO_FILENAME);
                $values = require $file;
                if (! is_array($values)) continue;
                foreach ($this->flatten($values, $group . '.') as $k => $v) {
                    $flat[$k] = $v;
                }
            }
        }

        $jsonPath = base_path("lang/{$locale}.json");
        if (is_file($jsonPath)) {
            $json = json_decode(file_get_contents($jsonPath) ?: '{}', true);
            if (is_array($json)) {
                foreach ($json as $k => $v) {
                    $flat[$k] = (string) $v;
                }
            }
        }

        return $flat;
    }

    /**
     * @param  array<string,mixed>  $array
     * @return array<string,string>
     */
    protected function flatten(array $array, string $prefix = ''): array
    {
        $out = [];
        foreach ($array as $key => $value) {
            $compoundKey = $prefix . $key;
            if (is_array($value)) {
                $out = array_merge($out, $this->flatten($value, $compoundKey . '.'));
            } else {
                $out[$compoundKey] = is_scalar($value) ? (string) $value : '';
            }
        }
        return $out;
    }
}
