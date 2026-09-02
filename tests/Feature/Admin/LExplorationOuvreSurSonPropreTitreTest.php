<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * L'EXPLORATION ANALYTIQUE OUVRE SUR SON PROPRE TITRE.
 *
 * Trois blocs de l'empilement de pilotage passaient avant : le bandeau « Pilotage operationnel &
 * qualite plateforme », dont les quatre tuiles etaient figees dans le gabarit — « Tests 200 »,
 * quand la suite en compte plus de huit mille —, la « Checklist go-live », quatre cases sans etat
 * annoncant un moteur supprime, puis quatre cartes de raccourcis.
 *
 * Les quatre liens des raccourcis figurent tous au catalogue des modules : aucun ecran n'est
 * devenu injoignable par ce retrait.
 */
class LExplorationOuvreSurSonPropreTitreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'esperluette du bandeau n'est PAS echappee dans le gabarit, d'ou le `false` des assertions :
     * `assertSee` echappe par defaut et chercherait `&amp;`.
     *
     * @return array<string, array{0: string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'bandeau de pilotage' => ['Pilotage opérationnel & qualité plateforme'],
            'checklist go-live' => ['Checklist go-live'],
            'cartes de raccourcis' => ['Suivre les indicateurs de performance.'],
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
     * TEMOIN — chaque phrase reste VISIBLE la ou l'empilement est encore inclus.
     *
     * Sans lui, les refus ci-dessus passeraient au vert sur une phrase mal orthographiee, un
     * accent perdu ou une esperluette echappee : ils mesureraient leur propre faute de frappe.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_ce_bloc_reste_visible_sur_le_tableau_de_bord(string $phrase): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee($phrase, false);
    }

    /** TEMOIN — la page rend son propre contenu : le retrait n'a pas casse sa racine unique. */
    public function test_temoin_la_page_ouvre_sur_son_titre(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertSee('Centre analytics', false)
            ->assertSee('Filtres analytics', false);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
