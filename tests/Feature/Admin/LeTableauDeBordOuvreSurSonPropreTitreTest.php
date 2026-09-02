<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LE TABLEAU DE BORD METIER OUVRE SUR SON PROPRE TITRE.
 *
 * Trois blocs passaient avant : un memo de securite — trois cartes de prose rappelant qu'il faut
 * « verifier les acces » et « controler les exports », sans un seul lien pour le faire —, puis les
 * empilements « preparation production » et « pilotage ».
 *
 * Le memo n'avait plus aucun appelant apres le retrait : supprime. Les liens des deux empilements
 * figurent au catalogue des modules, donc joignables ailleurs.
 */
class LeTableauDeBordOuvreSurSonPropreTitreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chaque phrase voyage avec la page qui la porte ENCORE, ou `null` si le bloc a disparu.
     * L'esperluette n'est pas echappee dans les gabarits, d'ou le `false` des assertions.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function blocsRetires(): array
    {
        return [
            'memo de securite' => ['Contrôler les exports, les restrictions par zone', null],
            'preparation production' => ['Centre de préparation production', '/admin/platform-readiness'],
            'pilotage' => ['Pilotage opérationnel & qualité plateforme', '/admin/emails'],
        ];
    }

    #[DataProvider('blocsRetires')]
    public function test_la_page_n_ouvre_plus_sur_ce_bloc(string $phrase, ?string $temoinUrl): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertDontSee($phrase, false);
    }

    /**
     * TEMOIN — chaque bloc survivant reste VISIBLE la ou il est encore inclus.
     *
     * Sans lui, les refus ci-dessus passeraient au vert sur une phrase mal orthographiee ou un
     * accent perdu : ils mesureraient leur propre faute de frappe.
     */
    #[DataProvider('blocsRetires')]
    public function test_temoin_ce_bloc_reste_visible_la_ou_il_est_inclus(string $phrase, ?string $temoinUrl): void
    {
        if ($temoinUrl === null) {
            $this->assertFalse(view()->exists('livewire.admin.governance.security-checks'),
                'Le memo de securite existe encore alors que plus aucune vue ne l’inclut.');

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
            ->get('/admin/business-dashboard')
            ->assertOk()
            ->assertSee('Business Dashboard', false)
            ->assertSee('CA ce mois', false);
    }

    /**
     * TEMOIN — la pile de scripts a survecu au retrait.
     *
     * `@push('scripts')` vivait AU-DESSUS des blocs supprimes : l'emporter aurait prive la page de
     * sa bibliotheque de graphiques, sans erreur visible cote serveur.
     *
     * LE CONTROLE EST SUR LA SOURCE, PAS SUR LA REPONSE : `TestCase::setUp()` appelle
     * `withoutVite()`, donc `@vite` ne rend RIEN en test. Une assertion HTTP sur le nom de l'actif
     * serait rouge quoi qu'il arrive — et, si elle etait inversee, verte pour une mauvaise raison.
     */
    public function test_temoin_les_graphiques_gardent_leur_bibliotheque(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/livewire/admin/business-dashboard.blade.php')
        );

        $this->assertStringContainsString("@push('scripts')", $source);
        $this->assertStringContainsString("@vite(['resources/js/apexcharts.js'])", $source);
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
