<?php

namespace Tests\Feature\I18n;

use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TranslationOverrideLoaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_override_takes_priority_over_file(): void
    {
        App::setLocale('fr');

        $beforeOverride = __('app.account');

        TranslationOverride::create([
            'locale' => 'fr',
            'group' => 'app',
            'key' => 'account',
            'value' => 'Compte (override DB)',
            'namespace' => '*',
            'is_published' => true,
        ]);

        Cache::flush();
        app('translator')->setLoaded([]);

        $afterOverride = __('app.account');

        $this->assertSame('Compte (override DB)', $afterOverride);
        $this->assertNotSame($beforeOverride, $afterOverride);
    }

    public function test_override_unpublished_is_not_applied(): void
    {
        App::setLocale('fr');

        $defaultValue = __('app.login');

        TranslationOverride::create([
            'locale' => 'fr',
            'group' => 'app',
            'key' => 'login',
            'value' => 'Connexion (draft)',
            'namespace' => '*',
            'is_published' => false,
        ]);

        Cache::flush();
        app('translator')->setLoaded([]);

        $this->assertSame($defaultValue, __('app.login'));
    }

    public function test_override_deleted_falls_back_to_file(): void
    {
        App::setLocale('fr');

        $defaultValue = __('app.logout');

        $override = TranslationOverride::create([
            'locale' => 'fr',
            'group' => 'app',
            'key' => 'logout',
            'value' => 'Quitter',
            'namespace' => '*',
            'is_published' => true,
        ]);

        Cache::flush();
        app('translator')->setLoaded([]);
        $this->assertSame('Quitter', __('app.logout'));

        $override->delete();
        Cache::flush();
        app('translator')->setLoaded([]);

        $this->assertSame($defaultValue, __('app.logout'));
    }
}
