<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * UN `<select>` SANS LARGEUR SE DIMENSIONNE SUR SON OPTION LA PLUS LONGUE.
 *
 * Mesure sur /admin/audit/logs, colonnes de 282 px :
 *   « Action »    403 px  -> +121 px par-dessus « Acteur »
 *   « Zone »      313 px  -> +31 px
 *   « Recherche » 216 px, « Résultats » 76 px  -> une rangee en dents de scie
 *
 * La grille n'y etait pour rien : elle posait bien quatre colonnes egales. Ce sont les champs
 * qui debordaient de leur cellule, faute de largeur. Corrige dans le systeme — huit vues
 * partagent `brio-filter-grid` — et non dans la page.
 */
class UnFiltreResteDansSaColonneTest extends TestCase
{
    private function toolMode(): string
    {
        return (string) file_get_contents(resource_path('css/tool-mode.css'));
    }

    public function test_un_champ_de_filtre_remplit_sa_cellule(): void
    {
        $css = $this->toolMode();

        foreach (['brio-filter-grid', 'brio-filter-grid-wide'] as $grille) {
            $debut = strpos($css, ".{$grille} :is(input");
            $this->assertNotFalse($debut, "Aucune regle de champ pour .{$grille}.");

            $bloc = substr($css, $debut, (int) strpos($css, '}', $debut) - $debut);

            $this->assertStringContainsString('select', $bloc);
            $this->assertStringContainsString('w-full', $bloc,
                "Les champs de .{$grille} n'ont pas de largeur : un select debordera.");
        }
    }

    public function test_une_cellule_peut_retrecir(): void
    {
        // Sans `min-w-0`, une option longue elargit la COLONNE et pousse les voisines.
        $this->assertMatchesRegularExpression(
            '/\.brio-filter-grid > \*,\s*\.brio-filter-grid-wide > \*\s*\{[^}]*min-w-0/s',
            $this->toolMode(),
        );
    }

    public function test_une_case_a_cocher_ne_s_etire_pas(): void
    {
        // TEMOIN A L'ENVERS : `w-full` sur une case a cocher ferait une barre en travers.
        $this->assertStringContainsString(
            ':is(input:not([type="checkbox"]):not([type="radio"]), select, textarea)',
            $this->toolMode(),
        );
    }

    public function test_temoin_le_balayage_lit_bien_la_feuille(): void
    {
        // Sans ce controle, les trois tests ci-dessus passeraient au vert sur un fichier vide.
        $this->assertStringContainsString('.brio-filter-grid       {', $this->toolMode());
    }
}
