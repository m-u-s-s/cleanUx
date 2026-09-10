<?php

namespace App\Providers;

use App\Models\TranslationOverride;
use App\Services\I18n\LocaleResolver;
use App\Services\I18n\TranslationOverrideLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

class I18nServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LocaleResolver::class);

        $this->app->extend('translation.loader', function (Loader $loader) {
            return new TranslationOverrideLoader($loader);
        });
    }

    public function boot(): void
    {
        // Invalider le cache des overrides quand un override change
        TranslationOverride::saved(function (TranslationOverride $override) {
            $this->oublier($override);
        });

        TranslationOverride::deleted(function (TranslationOverride $override) {
            $this->oublier($override);
        });
    }

    /**
     * Purger un override, des DEUX endroits où il vit.
     *
     * Vider le cache applicatif ne suffisait pas : le traducteur de Laravel garde en plus les
     * groupes déjà chargés EN MÉMOIRE du processus. Une correction enregistrée puis relue dans
     * la même requête — l'écran qui s'enregistre et se réaffiche — servait encore l'ancien
     * texte, et le vert du cache masquait le défaut.
     */
    protected function oublier(TranslationOverride $override): void
    {
        TranslationOverrideLoader::flushCache(
            $override->locale,
            $override->group,
        );

        $traducteur = $this->app['translator'] ?? null;

        if ($traducteur instanceof Translator) {
            $traducteur->setLoaded([]);
        }
    }
}
