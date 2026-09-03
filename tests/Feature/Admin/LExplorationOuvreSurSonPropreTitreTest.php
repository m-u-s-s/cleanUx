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
     * Chaque ligne porte sa phrase et le gabarit qui la portait. Les trois ont quitte le produit
     * le 2026-09-03, avec leur derniere page porteuse.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'bandeau de pilotage' => ['Pilotage opérationnel & qualité plateforme',
                'livewire.admin.pilotage.phase2s-banner'],
            'checklist go-live' => ['Checklist go-live',
                'livewire.admin.pilotage.go-live-checklist'],
            'cartes de raccourcis' => ['Suivre les indicateurs de performance.',
                'livewire.admin.pilotage.quick-actions'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, string $gabarit): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/analytics/exploration')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — chaque gabarit a bien quitte le disque.
     *
     * Tant qu'un porteur subsistait, le temoin etait une page ou la phrase restait visible. Le
     * dernier est parti : c'est desormais la disparition du gabarit qui garantit que la phrase
     * cherchee etait bien la sienne, et non une faute de frappe qui rougirait a tort.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_le_gabarit_a_quitte_le_disque(string $phrase, string $gabarit): void
    {
        $this->assertFalse(view()->exists($gabarit),
            "Le gabarit {$gabarit} existe encore alors que plus aucune vue ne l’inclut.");
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
