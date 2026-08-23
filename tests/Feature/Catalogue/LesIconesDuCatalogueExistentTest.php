<?php

namespace Tests\Feature\Catalogue;

use Database\Seeders\ReferencePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UNE ICÔNE INCONNUE NE PROTESTE PAS — ELLE DEVIENT UN CERCLE.
 *
 * `<x-ui.icon>` termine par `$icons[$name] ?? $icons['circle']`. Un nom absent de la table rend
 * donc une pastille ronde, sans erreur, sans avertissement, sans rien dans les journaux.
 *
 * `sectors.icon` et `trades.icon` sont remplies par les semeurs avec des NOMS. Sept d'entre eux
 * n'existaient pas dans le composant — `car`, `broom`, `leaf`, `paint-roller`, `pencil-square`,
 * `user-group`, `window`. Le secteur Mobilité et six métiers s'affichaient tous avec le même rond.
 *
 * Ce test ferme la classe entière plutôt qu'un cas : n'importe quel semeur qui introduirait demain
 * un nom non défini le fera échouer, au lieu de livrer un rond de plus.
 */
class LesIconesDuCatalogueExistentTest extends TestCase
{
    use RefreshDatabase;

    /** Ce que rend le composant quand il ne connaît pas le nom demandé. */
    private function rendu(string $nom): string
    {
        return Blade::render('<x-ui.icon :name="$n" />', ['n' => $nom]);
    }

    public function test_aucune_icone_du_catalogue_ne_retombe_sur_le_cercle(): void
    {
        $this->seed(ReferencePlatformSeeder::class);

        $repli = $this->rendu('nom-qui-n-existe-pas-du-tout');

        $noms = DB::table('sectors')->whereNotNull('icon')->pluck('icon', 'slug')
            ->merge(DB::table('trades')->whereNotNull('icon')->pluck('icon', 'slug'))
            ->filter(fn ($icone) => $icone !== '');

        $this->assertGreaterThan(10, $noms->count(), 'Le catalogue semé ne porte presque aucune icône : la mesure ne prouverait rien.');

        foreach ($noms as $slug => $icone) {
            $this->assertNotSame(
                $repli,
                $this->rendu($icone),
                "L'icône `{$icone}` de `{$slug}` n'existe pas dans <x-ui.icon> : elle s'affiche en cercle.",
            );
        }
    }

    /**
     * TÉMOIN POSITIF — le repli existe bel et bien, et se reconnaît.
     *
     * Sans ce contrôle, le test ci-dessus passerait au vert si `rendu()` renvoyait n'importe quoi
     * de toujours différent : il comparerait deux valeurs qui ne se ressemblent jamais.
     */
    public function test_temoin_un_nom_inconnu_rend_bien_le_cercle_de_repli(): void
    {
        $repli = $this->rendu('nom-qui-n-existe-pas-du-tout');

        $this->assertStringContainsString('<circle', $repli);
        $this->assertSame($repli, $this->rendu('un-autre-nom-tout-aussi-absent'));

        // Et une icône connue, elle, rend autre chose.
        $this->assertNotSame($repli, $this->rendu('hammer'));
    }

    /**
     * LES SEPT QUI MANQUAIENT, NOMMÉES UNE PAR UNE.
     *
     * Le test global ci-dessus dépend de ce que les semeurs écrivent aujourd'hui. Celui-ci fixe le
     * lot précis qui a été ajouté, pour qu'une suppression accidentelle dise LAQUELLE.
     */
    public function test_les_sept_icones_ajoutees_sont_bien_definies(): void
    {
        $repli = $this->rendu('nom-qui-n-existe-pas-du-tout');

        foreach (['car', 'broom', 'leaf', 'paint-roller', 'pencil-square', 'user-group', 'window'] as $icone) {
            $this->assertNotSame($repli, $this->rendu($icone), "L'icône `{$icone}` a disparu du composant.");
        }
    }
}
