<?php

namespace App\Providers;

use App\Models\TranslationOverride;
use App\Services\I18n\LocaleResolver;
use App\Services\I18n\TranslationOverrideLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\ServiceProvider;

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
            TranslationOverrideLoader::flushCache(
                $override->locale,
                $override->group,
            );
        });

        TranslationOverride::deleted(function (TranslationOverride $override) {
            TranslationOverrideLoader::flushCache(
                $override->locale,
                $override->group,
            );
        });
    }
}
