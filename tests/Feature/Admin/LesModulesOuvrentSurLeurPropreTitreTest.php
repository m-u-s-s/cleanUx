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
     * Chaque ligne porte sa phrase, la page qui la montre ENCORE (ou `null` si le bloc a quitte
     * le produit), et le gabarit dont on verifie alors la disparition.
     *
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'memo de commandes' => ['Commandes de validation recommandées', null,
                'livewire.admin.governance.command-hints'],
            'preparation production' => ['Centre de préparation production', '/admin/platform-readiness',
                'livewire.admin.readiness.hero'],
            'pilotage' => ['Pilotage opérationnel & qualité plateforme', null,
                'livewire.admin.pilotage.phase2s-banner'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, ?string $temoinUrl, string $gabarit): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/modules')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — chaque bloc est verifie LA OU IL EST, pas ailleurs.
     *
     * Un bloc encore inclus doit rester VISIBLE sur sa page ; un bloc retire du produit doit avoir
     * disparu du disque. Sans cela les refus ci-dessus passeraient au vert sur une phrase mal
     * orthographiee ou un accent perdu : ils mesureraient leur propre faute de frappe.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_ce_bloc_est_verifie_la_ou_il_est(string $phrase, ?string $temoinUrl, string $gabarit): void
    {
        if ($temoinUrl === null) {
            // Le bloc a quitte le produit : c'est sa disparition qui garantit que la phrase
            // cherchee etait bien la sienne.
            $this->assertFalse(view()->exists($gabarit),
                "Le gabarit {$gabarit} existe encore alors que plus aucune vue ne l’inclut.");

            return;
        }

        $this->actingAs($this->admin())
            ->get($temoinUrl)
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
