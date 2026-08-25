<?php

namespace Tests\Feature\DesignSystem;

use App\Livewire\Admin\BusinessDashboard;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LES SERIES CALCULEES DOIVENT ATTEINDRE UN GRAPHIQUE, ET LA VUE DOIT SE RENDRE.
 *
 * Trois tableaux de bord calculaient une serie depuis toujours et la rendaient en largeurs de
 * `div` : comparer deux semaines demandait de mesurer deux traits a l'oeil.
 *
 * Ce test rend VRAIMENT les composants. Les gardes de design lisent le fichier Blade ; ils ne
 * peuvent pas voir qu'une variable manque a l'appel ni qu'une expression imbriquee dans une
 * directive casse la compilation — un piege deja paye sur ce depot, dont l'erreur se signale
 * quarante lignes plus bas, sur un `@forelse` sans rapport.
 */
class LesGraphiquesSeRendentTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tableau_de_bord_metier_porte_son_graphique(): void
    {
        $admin = User::factory()->admin()->create([
            'permissions' => ['view-analytics', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $rendu = Livewire::test(BusinessDashboard::class)->html();

        $this->assertStringContainsString('dessinerActivite', $rendu);
        $this->assertStringContainsString('data-totaux', $rendu);

        // La devise VOYAGE avec la serie : sans elle l'axe affiche des nombres nus.
        $this->assertStringContainsString('data-devise', $rendu);
    }

    /**
     * TEMOIN — le graphique remplace les barres, il ne s'y ajoute pas.
     *
     * Sans ce controle, la vue pourrait porter les deux : le graphique passerait le test
     * precedent pendant que les six traits empiles restent en dessous.
     */
    public function test_temoin_les_barres_de_largeur_ont_disparu(): void
    {
        $vue = file_get_contents(resource_path('views/livewire/admin/business-dashboard.blade.php'));

        $this->assertStringNotContainsString("\$week['revenue'] / \$max", $vue);
    }

    public function test_le_tableau_de_bord_societe_porte_son_anneau(): void
    {
        $societe = OrganizationAccount::factory()->create(['status' => 'active']);

        $responsable = User::factory()->create([
            'organization_account_id' => $societe->id,
            'current_organization_id' => $societe->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $societe->id,
            'user_id' => $responsable->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($responsable);

        // Sans reservation, l'anneau n'a rien a montrer : la vue doit se rendre quand meme.
        Livewire::test(ClientCompanyDashboard::class)->assertOk();
    }

    /** La jauge circulaire etait DEFINIE en CSS et n'avait AUCUN appelant. */
    public function test_la_jauge_a_desormais_un_appelant(): void
    {
        $heros = file_get_contents(resource_path('views/livewire/employe/dashboard/hero.blade.php'));

        $this->assertStringContainsString('brio-jauge', $heros);
        $this->assertStringContainsString('--brio-jauge-part', $heros);
        $this->assertStringContainsString('role="img"', $heros);
    }
}
