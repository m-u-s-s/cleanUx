<?php

namespace Tests\Feature\Catalogue;

use Database\Seeders\ReferencePlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** UNE ICÔNE INCONNUE NE PROTESTE PAS — ELLE DEVIENT UN CERCLE. */
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

        // Toutes les icones manquantes d'un coup : sept l'etaient le jour ou ce test a ete ecrit,
        // et une assertion par tour n'en aurait nomme qu'une.
        $rondes = [];

        foreach ($noms as $slug => $icone) {
            if ($this->rendu($icone) === $repli) {
                $rondes[] = "{$slug} : {$icone}";
            }
        }

        $this->assertSame([], $rondes, 'Ces icones du catalogue s affichent en cercle.');
    }

    /** TÉMOIN POSITIF — le repli existe bel et bien, et se reconnaît. */
    public function test_temoin_un_nom_inconnu_rend_bien_le_cercle_de_repli(): void
    {
        $repli = $this->rendu('nom-qui-n-existe-pas-du-tout');

        $this->assertStringContainsString('<circle', $repli);
        $this->assertSame($repli, $this->rendu('un-autre-nom-tout-aussi-absent'));

        // Et une icône connue, elle, rend autre chose.
        $this->assertNotSame($repli, $this->rendu('hammer'));
    }

    /** LES SEPT QUI MANQUAIENT, NOMMÉES UNE PAR UNE. */
    public function test_les_sept_icones_ajoutees_sont_bien_definies(): void
    {
        $repli = $this->rendu('nom-qui-n-existe-pas-du-tout');

        $disparues = array_values(array_filter(
            ['car', 'broom', 'leaf', 'paint-roller', 'pencil-square', 'user-group', 'window'],
            fn (string $icone) => $this->rendu($icone) === $repli,
        ));

        $this->assertSame([], $disparues, 'Ces icones ont disparu du composant.');
    }
}
