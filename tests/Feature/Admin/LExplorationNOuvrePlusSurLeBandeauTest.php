<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'EXPLORATION ANALYTIQUE N'OUVRE PLUS SUR UN BANDEAU DECORATIF.
 *
 * La page portait DEUX en-tetes : le bandeau « Pilotage operationnel & qualite plateforme », dont
 * les quatre tuiles etaient figees dans le gabarit — « Tests 200 », quand la suite en compte plus
 * de huit mille — puis seulement le vrai titre de la page.
 *
 * Les raccourcis du meme empilement restent : eux portent quatre liens reels, garde par permission.
 */
class LExplorationNOuvrePlusSurLeBandeauTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'ESPERLUETTE N'EST PAS ECHAPPEE dans le gabarit. `assertSee` echappe par defaut et
     * chercherait `&amp;` : les deux assertions ci-dessous passent donc `false` en second argument.
     */
    private const BANDEAU = 'Pilotage opérationnel & qualité plateforme';

    public function test_la_page_n_ouvre_plus_sur_le_bandeau(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertDontSee(self::BANDEAU, false);
    }

    /**
     * TEMOIN — la phrase est bien VISIBLE la ou le bandeau est encore inclus.
     *
     * Sans lui, l'assertion ci-dessus passerait au vert avec une phrase mal orthographiee, un
     * accent perdu ou une esperluette echappee : elle mesurerait sa propre faute de frappe.
     */
    public function test_temoin_le_bandeau_reste_visible_sur_les_pages_qui_l_incluent(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee(self::BANDEAU, false);
    }

    /** TEMOIN — la page rend toujours son propre contenu ; le retrait n'a pas emporte l'ecran. */
    public function test_temoin_la_page_rend_toujours_son_contenu(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertSee('Centre analytics', false);
    }

    /** TEMOIN — les raccourcis du meme empilement sont restes : c'est le bandeau qui part, pas la pile. */
    public function test_temoin_les_raccourcis_sont_restes(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertSee('Suivre les indicateurs de performance.', false);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
