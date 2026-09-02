<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * L'EXPLORATION ANALYTIQUE N'OUVRE PLUS SUR DEUX BLOCS DECORATIFS.
 *
 * La page portait trois en-tetes avant son propre titre : le bandeau « Pilotage operationnel &
 * qualite plateforme », dont les quatre tuiles etaient figees dans le gabarit — « Tests 200 »,
 * quand la suite en compte plus de huit mille — puis la « Checklist go-live », quatre cases sans
 * etat qui annoncaient encore un moteur supprime.
 *
 * Les raccourcis du meme empilement restent : eux portent quatre liens reels, gardes par permission.
 */
class LExplorationNOuvrePlusSurLeBandeauTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function blocsRetires(): array
    {
        return [
            // L'ESPERLUETTE N'EST PAS ECHAPPEE dans le gabarit, d'ou le `false` des assertions.
            'bandeau de pilotage' => ['Pilotage opérationnel & qualité plateforme'],
            'checklist go-live' => ['Checklist go-live'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — chaque phrase est bien VISIBLE la ou les blocs sont encore inclus.
     *
     * Sans lui, les refus ci-dessus passeraient au vert sur une phrase mal orthographiee, un accent
     * perdu ou une esperluette echappee : ils mesureraient leur propre faute de frappe.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_ce_bloc_reste_visible_sur_les_pages_qui_l_incluent(string $phrase): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee($phrase, false);
    }

    /** TEMOIN — la page rend toujours son propre contenu ; le retrait n'a pas emporte l'ecran. */
    public function test_temoin_la_page_rend_toujours_son_contenu(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertSee('Centre analytics', false);
    }

    /** TEMOIN — les raccourcis du meme empilement sont restes : ce sont les blocs figes qui partent. */
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
