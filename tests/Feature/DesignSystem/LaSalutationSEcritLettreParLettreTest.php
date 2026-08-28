<?php

namespace Tests\Feature\DesignSystem;

use App\Livewire\ClientDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/** La salutation du tableau de bord client s'ecrit lettre par lettre, comme a la machine. */
class LaSalutationSEcritLettreParLettreTest extends TestCase
{
    use RefreshDatabase;

    private function rendre(string $texte): string
    {
        return Blade::render('<x-ui.typed-text :text="$t" />', ['t' => $texte]);
    }

    public function test_chaque_lettre_porte_son_rang(): void
    {
        $rendu = $this->rendre('Bon');

        $this->assertStringContainsString('--cx-typed-i:0', $rendu);
        $this->assertStringContainsString('--cx-typed-i:1', $rendu);
        $this->assertStringContainsString('--cx-typed-i:2', $rendu);
        $this->assertSame(3, substr_count($rendu, 'cx-typed__char'));
    }

    /** Les accents comptent pour une lettre : `mb_str_split`, jamais `str_split`. */
    public function test_les_accents_ne_sont_pas_coupes_en_deux(): void
    {
        $rendu = $this->rendre('après');

        $this->assertSame(5, substr_count($rendu, 'cx-typed__char'));
        $this->assertStringContainsString('>è<', $rendu);
        $this->assertStringNotContainsString('?', $rendu);
    }

    /** Le rang continue de croitre APRES l'espace : le mot suivant demarre plus tard. */
    public function test_l_espace_avance_le_rythme(): void
    {
        $rendu = $this->rendre('Bon jour');

        $this->assertStringContainsString('--cx-typed-i:4', $rendu);
        $this->assertStringContainsString('--cx-typed-n:8', $rendu);
    }

    /** Un mot = un bloc insecable, sinon un titre se couperait au milieu d'un mot. */
    public function test_les_mots_restent_insecables(): void
    {
        $rendu = $this->rendre('Bon apres-midi Client');

        $this->assertSame(3, substr_count($rendu, 'cx-typed__word'));
    }

    /** Le curseur vit DANS le dernier mot, sinon il retombe seul a la ligne suivante. */
    public function test_le_curseur_suit_le_dernier_mot(): void
    {
        $rendu = $this->rendre('Bon jour');

        $this->assertSame(1, substr_count($rendu, 'cx-typed__caret'));
        $this->assertMatchesRegularExpression(
            '/>r<\/span><span class="cx-typed__caret"><\/span><\/span>/u',
            $rendu,
            'Le curseur ne suit pas la derniere lettre du dernier mot.',
        );
    }

    public function test_le_texte_complet_reste_entier_pour_un_lecteur_d_ecran(): void
    {
        $rendu = $this->rendre('Bon après-midi Client');

        $this->assertMatchesRegularExpression(
            '/<span class="sr-only">\s*Bon après-midi Client\s*<\/span>/u',
            $rendu,
        );
        $this->assertStringContainsString('aria-hidden="true"', $rendu);
    }

    public function test_le_texte_est_echappe(): void
    {
        $rendu = $this->rendre('<script>x</script>');

        $this->assertStringNotContainsString('<script>', $rendu);
        $this->assertStringContainsString('&lt;', $rendu);
    }

    public function test_la_coquille_de_page_anime_son_titre_quand_on_le_demande(): void
    {
        $rendu = Blade::render('<x-page-shell title="Bon après-midi" typed />');

        $this->assertStringContainsString('cx-typed__char', $rendu);
    }

    /**
     * TEMOIN. Sans lui, les tests ci-dessus passeraient au vert alors que les 21 AUTRES vues
     * qui emploient `x-page-shell` se seraient mises a animer leur titre.
     */
    public function test_temoin_la_coquille_de_page_rend_le_titre_nu_par_defaut(): void
    {
        $rendu = Blade::render('<x-page-shell title="Missions du jour" />');

        $this->assertStringNotContainsString('cx-typed', $rendu);
        $this->assertStringContainsString('Missions du jour', $rendu);
    }

    public function test_la_salutation_du_tableau_de_bord_client_est_animee(): void
    {
        $client = User::factory()->client()->create(['name' => 'Camille Dupont']);

        Livewire::actingAs($client)->test(ClientDashboard::class)
            ->assertOk()
            ->assertSee('cx-typed__char', escape: false)
            ->assertSee('Camille', escape: false);
    }

    /** La feuille definit l'animation ET son repli sans mouvement. */
    public function test_la_feuille_de_mouvement_porte_l_animation_et_son_repli(): void
    {
        $css = (string) file_get_contents(resource_path('css/motion.css'));

        $this->assertStringContainsString('@keyframes cx-typed-in', $css);
        $this->assertStringContainsString('.cx-typed__char', $css);
        $this->assertStringContainsString('.cx-typed__caret', $css);

        preg_match_all('/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/', $css, $blocs);

        $this->assertNotEmpty($blocs[1], 'La feuille ne porte aucun bloc de mouvement reduit.');
        $this->assertStringContainsString('.cx-typed__char', implode(PHP_EOL, $blocs[1]),
            'Le mouvement reduit ne neutralise pas la machine a ecrire.');
    }
}
