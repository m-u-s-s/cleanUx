<?php

namespace Tests\Feature\Catalogue;

use App\Models\Sector;
use App\Models\Trade;
use Database\Seeders\CatalogueTraductionsSeeder;
use Database\Seeders\ReferencePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * UN CATALOGUE TRADUISIBLE ET VIDE RESTE MONOLINGUE À L'ÉCRAN.
 *
 * `Sector` et `Trade` savent porter des traductions depuis le 2026-08-22. Le semeur qui les
 * remplit, `CatalogueTraductionsSeeder`, existait lui aussi — complet, idempotent, documenté — et
 * AUCUNE chaîne ne l'appelait. Il portait la mention « Usage : php artisan db:seed --class=… ».
 *
 * Conséquence mesurée après un `migrate:fresh --seed` : `catalog_translations` contenait ZÉRO
 * ligne. Un visiteur néerlandophone, anglophone, hispanophone, italophone ou germanophone voyait
 * un catalogue entièrement français.
 *
 * C'est la forme la plus discrète de la famille de défauts dominante de ce dépôt : non pas du code
 * absent, mais une capacité complète que rien n'alimente.
 *
 * ── CE QUE CE TEST GARDE, ET CE QU'IL NE GARDE PAS ───────────────────────────────────────────
 *
 * Les NOMS, dans les langues ACTIVES. Les accroches et les descriptions relèvent de la plume
 * commerciale et restent délibérément à la main de l'exploitant — les inventer produirait un texte
 * que personne n'a choisi. `pt` est configuré mais désactivé : le semeur ne l'écrit pas, et ce test
 * ne l'exige pas non plus.
 */
class LeCatalogueEstTraduitDansLesLanguesActivesTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> Les langues actives, hors langue par défaut. */
    private function languesActives(): array
    {
        $defaut = (string) Config::get('i18n.default', 'fr');

        return collect((array) Config::get('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->keys()
            ->reject(fn ($code) => $code === $defaut)
            ->map(fn ($code) => (string) $code)
            ->values()
            ->all();
    }

    public function test_temoin_il_y_a_bien_des_langues_actives_et_un_catalogue(): void
    {
        /*
         * TÉMOIN POSITIF. Sans lui, le test principal passerait au vert dans deux cas où il ne
         * mesure rien : aucune langue active, ou aucun objet de catalogue.
         */
        $this->seed(ReferencePlatformSeeder::class);

        $this->assertGreaterThanOrEqual(4, count($this->languesActives()), 'Presque aucune langue active.');
        $this->assertGreaterThanOrEqual(6, Sector::query()->count());
        $this->assertGreaterThanOrEqual(16, Trade::query()->count());
    }

    public function test_chaque_secteur_et_chaque_metier_porte_son_nom_dans_toutes_les_langues_actives(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $langues = $this->languesActives();
        $manquantes = [];

        foreach ([Sector::class, Trade::class] as $classe) {
            foreach ($classe::query()->get() as $objet) {
                foreach ($langues as $langue) {
                    $valeur = $objet->translations()
                        ->where('field', 'name')
                        ->where('locale', $langue)
                        ->value('value');

                    if (blank($valeur)) {
                        $manquantes[] = sprintf('%s `%s` → %s', class_basename($classe), $objet->slug, $langue);
                    }
                }
            }
        }

        $this->assertSame([], $manquantes, 'Ces entrées du catalogue restent en français pour ces visiteurs.');
    }

    /**
     * LE SEMEUR N'ÉCRASE JAMAIS UNE SAISIE.
     *
     * Il propose un point de départ ; l'exploitant décide. Sans ce contrôle, un second passage —
     * ou un déploiement — effacerait la traduction retouchée à la main.
     */
    public function test_une_traduction_deja_saisie_survit_a_un_second_passage(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $metier = Trade::query()->where('slug', 'peinture')->firstOrFail();
        $metier->setTranslation('name', 'en', 'Bespoke painting');

        $this->seed(CatalogueTraductionsSeeder::class);

        $this->assertSame(
            'Bespoke painting',
            $metier->translations()->where('field', 'name')->where('locale', 'en')->value('value'),
        );
    }
}
