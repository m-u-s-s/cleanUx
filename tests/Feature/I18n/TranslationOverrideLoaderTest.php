<?php

namespace Tests\Feature\I18n;

use App\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    /**
     * Le chargeur lit la locale ENTIÈRE d'un coup, pas un groupe à la fois.
     * Une page qui touchait cinq groupes faisait cinq requêtes, plus un `hasTable` à chacune.
     */
    public function test_plusieurs_groupes_ne_coutent_qu_une_requete(): void
    {
        TranslationOverride::create([
            'locale' => 'fr', 'group' => 'app', 'namespace' => '*',
            'key' => 'sonde_app', 'value' => 'A', 'is_published' => true,
        ]);
        TranslationOverride::create([
            'locale' => 'fr', 'group' => 'ui', 'namespace' => '*',
            'key' => 'sonde_ui', 'value' => 'U', 'is_published' => true,
        ]);

        Cache::flush();
        $this->app->forgetInstance('translator');
        app('translator')->setLocale('fr');

        $requetes = [];
        DB::listen(function ($q) use (&$requetes) {
            if (str_contains($q->sql, 'translation_overrides')) {
                $requetes[] = json_encode($q->bindings);
            }
        });

        // Quatre groupes distincts, dont deux qui portent vraiment un override.
        $rendus = [__('app.sonde_app'), __('ui.sonde_ui'), __('validation.sonde'), __('auth.sonde')];

        // TÉMOIN — sans lui, un chargeur débranché rendrait 0 requête ET 0 traduction,
        // et le test passerait en mesurant une panne.
        $this->assertSame('A', $rendus[0], 'L’override du groupe `app` ne s’applique plus.');
        $this->assertSame('U', $rendus[1], 'L’override du groupe `ui` ne s’applique plus.');

        /*
         * DEUX au plus, et ce sont les seules irreductibles :
         *   1. `Schema::hasTable`, memoise des que la table est trouvee ;
         *   2. l'instantane de la locale, qui porte TOUS ses groupes.
         * Au-dela, le chargeur est reparti en une requete par groupe.
         */
        $this->assertLessThanOrEqual(
            2,
            count($requetes),
            'Une requete par GROUPE de traduction : '.implode(' // ', $requetes),
        );
    }
}
