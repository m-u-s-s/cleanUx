<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE CENTRE DE CONTROLE DES MODULES OUVRE SUR SON PROPRE TITRE.
 *
 * Trois blocs passaient avant : un memo listant `php artisan optimize:clear`, `php artisan test`
 * et `git status` — des commandes de developpeur affichees en decor sur un ecran d'administration
 * —, puis les empilements « preparation production » et « pilotage ».
 *
 * Le memo n'avait plus aucun appelant apres le retrait : supprime. Les liens des deux empilements
 * figurent au catalogue des modules, donc joignables ailleurs.
 */
class LesModulesOuvrentSurLeurPropreTitreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * L'esperluette du bandeau n'est PAS echappee dans le gabarit, d'ou le `false` des assertions :
     * `assertSee` echappe par defaut et chercherait `&amp;`.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function blocsRetires(): array
    {
        return [
            'memo de commandes' => ['Commandes de validation recommandées', false],
            'preparation production' => ['Centre de préparation production', true],
            'pilotage' => ['Pilotage opérationnel & qualité plateforme', true],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, bool $survitAilleurs): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/modules')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — les blocs encore inclus ailleurs y restent VISIBLES.
     *
     * Sans lui, les refus ci-dessus passeraient au vert sur une phrase mal orthographiee, un
     * accent perdu ou une esperluette echappee : ils mesureraient leur propre faute de frappe.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_ce_bloc_reste_visible_sur_le_tableau_de_bord(string $phrase, bool $survitAilleurs): void
    {
        if (! $survitAilleurs) {
            // Le memo de commandes n'a plus d'appelant : c'est sa disparition qui est verifiee.
            $this->assertFalse(view()->exists('livewire.admin.governance.command-hints'),
                'Le memo de commandes existe encore alors que plus aucune vue ne l’inclut.');

            return;
        }

        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee($phrase, false);
    }

    /** TEMOIN — la page rend son propre contenu : le retrait n'a pas casse sa racine unique. */
    public function test_temoin_la_page_ouvre_sur_son_titre(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/modules')
            ->assertOk()
            ->assertSee('Centre de contrôle des modules', false);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
