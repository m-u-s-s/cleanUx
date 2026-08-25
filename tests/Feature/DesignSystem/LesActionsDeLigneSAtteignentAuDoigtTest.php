<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UNE ACTION DE LIGNE N'EST PAS UN MOT SOULIGNE.
 *
 * « Modifier » et « Retirer » de l'ecran des disponibilites s'ecrivaient `text-xs
 * text-blue-600` : un texte nu, mesure a 51 x 16 et 42 x 16 pixels. Au-dessous du minimum
 * tactile, et sans zone de clic autour du mot. Un prestataire retire un creneau depuis son
 * telephone, entre deux interventions.
 *
 * CE DEFAUT ETAIT CACHE DERRIERE UNE PANNE DE L'OUTIL DE MESURE. Les seize pages du groupe
 * prestataire tombaient en HTTP 0 a chaque balayage complet : le harnais n'atteignait jamais
 * cette page. Reparer la connexion l'a revele du meme coup.
 */
class LesActionsDeLigneSAtteignentAuDoigtTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_classe_d_action_de_ligne_porte_une_hauteur_minimale(): void
    {
        $css = (string) file_get_contents(resource_path('css/composants.css'));

        $this->assertMatchesRegularExpression(
            '/\.brio-btn-ligne\s*\{[^}]*min-height:\s*2\.75rem/s',
            $css,
        );
    }

    public function test_l_ecran_des_disponibilites_l_emploie(): void
    {
        $vue = (string) file_get_contents(
            resource_path('views/livewire/employe/disponibilites-employe.blade.php')
        );

        $this->assertStringContainsString('brio-btn-ligne-accent', $vue);
        $this->assertStringContainsString('brio-btn-ligne-danger', $vue);
    }

    /**
     * TEMOIN — le texte nu qui portait le defaut a bien disparu.
     *
     * Sans ce controle, la nouvelle classe pourrait s'AJOUTER a l'ancienne : le test
     * precedent passerait pendant que `text-xs` continue de rapetisser le bouton.
     */
    public function test_temoin_le_texte_minuscule_a_disparu_des_actions(): void
    {
        $vue = (string) file_get_contents(
            resource_path('views/livewire/employe/disponibilites-employe.blade.php')
        );

        // On ne lit que les lignes de bouton : `text-xs` reste legitime ailleurs.
        foreach (explode("\n", $vue) as $ligne) {
            if (! str_contains($ligne, 'brio-btn-ligne')) {
                continue;
            }

            $this->assertStringNotContainsString('text-xs', $ligne, trim($ligne));
        }
    }
}
