<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES BOITES DU NAVIGATEUR NE DOIVENT PLUS PARLER A LA PLACE DU PRODUIT.
 *
 * Dix-huit `alert()` et `confirm()` vivaient dans les vues. Cette fenetre-la ignore le theme,
 * ignore la langue de la page, BLOQUE le fil du navigateur, et se signale hors du cadre sur
 * un telephone. Elle ne sait pas non plus distinguer une information d'une decision : « Mission
 * demarree » et « Revoquer definitivement ce jeton ? » y avaient exactement la meme allure.
 *
 * Ce test lit les fichiers de vue : c'est le seul endroit ou une reapparition se voit avant
 * qu'un utilisateur ne la rencontre.
 */
class PlusAucuneBoiteDuNavigateurTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function vues(): array
    {
        $trouvees = [];

        $iterateur = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterateur as $fichier) {
            if ($fichier->isFile() && str_ends_with($fichier->getFilename(), '.blade.php')) {
                $trouvees[] = $fichier->getPathname();
            }
        }

        return $trouvees;
    }

    public function test_aucune_vue_n_appelle_alert_ni_confirm(): void
    {
        $coupables = [];

        foreach ($this->vues() as $chemin) {
            /*
             * LES COMMENTAIRES SONT NEUTRALISES AVANT LA LECTURE, pas ligne par ligne.
             *
             * Ils expliquent POURQUOI ces appels ont disparu, et les citent pour cela. Un
             * filtre qui ne regarde que le DEBUT de chaque ligne laisse passer les blocs sur
             * plusieurs lignes : trois explications se faisaient accuser du defaut qu'elles
             * decrivent. Les lignes sont videes plutot que retirees, pour que les numeros
             * signales restent ceux du fichier.
             */
            $contenu = preg_replace_callback(
                '/\{\{--.*?--\}\}|\/\*.*?\*\//s',
                static fn (array $bloc): string => str_repeat("\n", substr_count($bloc[0], "\n")),
                (string) file_get_contents($chemin)
            ) ?? '';

            foreach (explode("\n", $contenu) as $numero => $ligne) {
                $nu = trim($ligne);

                if ($nu === '' || str_starts_with($nu, '//') || str_starts_with($nu, '*')) {
                    continue;
                }

                if (preg_match('/(?<![\w:.-])(alert|confirm)\s*\(/', $ligne) === 1) {
                    $coupables[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $chemin).':'.($numero + 1);
                }
            }
        }

        $this->assertSame([], $coupables, "Boites du navigateur retrouvees :\n".implode("\n", $coupables));
    }

    /**
     * TEMOIN — le detecteur trouve VRAIMENT un appel quand il y en a un.
     *
     * Sans ce controle, une expression trop stricte rendrait un tableau vide en permanence :
     * le test precedent passerait au vert en ne mesurant rien.
     */
    public function test_temoin_le_detecteur_reconnait_un_appel(): void
    {
        $lignes = [
            "            alert('Code invalide.');",
            '<button onclick="return confirm(\'Supprimer ?\')">',
            '    confirm(`Frais de ${x}.`);',
        ];

        foreach ($lignes as $ligne) {
            $this->assertSame(1, preg_match('/(?<![\w:.-])(alert|confirm)\s*\(/', $ligne), $ligne);
        }
    }

    /** TEMOIN — et il n'accuse pas ce qui n'en est pas. */
    public function test_temoin_le_detecteur_n_accuse_pas_a_tort(): void
    {
        $lignes = [
            '<button wire:confirm="Supprimer ?">',
            '    $this->dispatch(\'toast\', \'Fait.\');',
            '    window.brioToast({ message: \'Fait.\' });',
            '    x-on:click="$dispatch(\'brio-confirmer\', { appel: \'revoke(7)\' })"',
        ];

        foreach ($lignes as $ligne) {
            $this->assertSame(0, preg_match('/(?<![\w:.-])(alert|confirm)\s*\(/', $ligne), $ligne);
        }
    }

    public function test_le_point_d_entree_javascript_des_notifications_existe(): void
    {
        $js = (string) file_get_contents(resource_path('js/app.js'));

        // `echo-listeners.js` appelait cette fonction sans que personne ne la definisse :
        // les notifications temps reel retombaient sur une permission systeme jamais accordee.
        $this->assertStringContainsString('window.brioToast', $js);

        $toast = (string) file_get_contents(resource_path('views/components/toast.blade.php'));

        $this->assertStringContainsString('brio-toast.window', $toast);
    }

    public function test_les_deux_espaces_societe_montent_enfin_la_notification(): void
    {
        foreach (['client-company', 'provider-company'] as $espace) {
            $mise = (string) file_get_contents(resource_path("views/layouts/{$espace}.blade.php"));

            $this->assertStringContainsString('<x-toast />', $mise, $espace);
            $this->assertStringContainsString('<x-ui.confirmation />', $mise, $espace);
        }
    }
}
