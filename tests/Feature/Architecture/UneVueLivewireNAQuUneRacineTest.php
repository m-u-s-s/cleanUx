<?php

namespace Tests\Feature\Architecture;

use App\Console\Commands\AuditerLesRacinesDeVuesLivewire;
use Tests\TestCase;

/**
 * LIVEWIRE NE REND QUE LA PREMIERE RACINE — LE RESTE DISPARAIT EN SILENCE.
 *
 * Mesure du 2026-08-29, sur `client-company/site-manager` et `client-company/members-access` :
 * la modale etait posee APRES le `</div>` racine. Cliquer « Ajouter un local » envoyait bien
 * la requete, le serveur repondait 200 avec `showForm: true` dans l'instantane — et l'ecran
 * ne bougeait pas. Deux actions primaires de l'espace societe etaient mortes, et rien ne le
 * disait : ni exception, ni test rouge, ni erreur de console.
 *
 * Sept vues portaient ce defaut. La garde ci-dessous empeche la huitieme.
 */
class UneVueLivewireNAQuUneRacineTest extends TestCase
{
    public function test_aucune_vue_de_composant_ne_declare_plusieurs_racines(): void
    {
        $fautives = AuditerLesRacinesDeVuesLivewire::vuesAPlusieursRacines();

        $lignes = [];

        foreach ($fautives as $vue => $racines) {
            $lignes[] = sprintf('%s — %d racines', $vue, $racines);
        }

        $this->assertSame([], $lignes, sprintf(
            "%d vue(s) de composant déclarent plus d’un élément racine :\n  %s\n\n".
            'Repliez les blocs surnuméraires DANS la racine : Livewire ne rend que le premier.',
            count($lignes),
            implode("\n  ", $lignes),
        ));
    }

    /**
     * TEMOIN — la mesure sait reconnaitre une racine unique ET une racine de trop.
     *
     * Sans les deux sens, « zéro faute » pourrait aussi bien vouloir dire « la mesure ne
     * regarde rien ».
     */
    public function test_temoin_la_mesure_compte_bien_les_racines(): void
    {
        $compter = function (string $source): int {
            $methode = new \ReflectionMethod(AuditerLesRacinesDeVuesLivewire::class, 'compterLesRacines');
            $methode->setAccessible(true);

            return (int) $methode->invoke(null, $source);
        };

        $this->assertSame(1, $compter('<div><p>bonjour</p></div>'));
        $this->assertSame(2, $compter("<div>a</div>\n@if (\$x)\n<div>b</div>\n@endif"));

        // Une balise orpheline ne compte pas pour une racine ouverte.
        $this->assertSame(1, $compter('<div><img src="x"><br><input></div>'));

        // Un commentaire Blade contenant du HTML ne compte pas non plus.
        $this->assertSame(1, $compter("{{-- <div>commentaire</div> --}}\n<div>vrai</div>"));
    }

    /** TEMOIN — le balayage voit bien des vues ; sinon il ne mesure rien. */
    public function test_temoin_le_balayage_voit_des_vues(): void
    {
        $nombre = 0;
        $racine = resource_path('views/livewire');

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($racine));

        /** @var \SplFileInfo $fichier */
        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $nombre++;
            }
        }

        $this->assertGreaterThan(100, $nombre, 'Le balayage ne voit presque aucune vue Livewire.');
    }
}
