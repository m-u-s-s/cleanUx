<?php

namespace App\Support\Livewire\Concerns\Admin;

/** Les langues à traduire, dites à UN SEUL endroit. */
trait ManagesCatalogTranslations
{
    /**
     * Les langues à proposer : celles activées, moins celle des libellés de base.
     *
     * @return array<string, string>
     */
    public function translationLocales(): array
    {
        $default = (string) config('i18n.default', 'fr');

        return collect((array) config('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->reject(fn ($meta, $code) => $code === $default)
            ->map(fn ($meta) => (string) ($meta['native_name'] ?? $meta['name'] ?? ''))
            ->all();
    }
}
