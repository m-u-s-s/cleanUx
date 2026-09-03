<?php

namespace Tests\Feature\SuperAdmin;

use App\Livewire\SuperAdmin\SuperAdminDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ANNEAU DE RÉPARTITION DU SUPER-ADMINISTRATEUR.
 *
 * Il était le SEUL rôle sans aucun graphique, alors que sa page porte précisément la question
 * qu'un graphique répond mieux qu'une liste : « comment se répartit la population ? ». Six
 * chiffres alignés se comparent mal ; un anneau se lit d'un coup.
 *
 * L'anneau COMPLÈTE la liste, il ne la remplace pas : une part de 2 % reste illisible sur un
 * anneau, et le chiffre exact compte pour qui pilote.
 */
class RepartitionDeLaPopulationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        // LE SIEGE NE SE POSE QUE PAR SA PORTE : le modele refuse l'ecriture directe.
        return $this->prendreLeSiege(['role' => 'super_admin']);
    }

    public function test_l_anneau_s_affiche_quand_la_plateforme_a_des_comptes(): void
    {
        $patron = $this->superAdmin();
        User::factory()->client()->count(3)->create();
        User::factory()->employe()->count(2)->create();

        Livewire::actingAs($patron)
            ->test(SuperAdminDashboard::class)
            ->assertSee('Répartition de la population')
            ->assertSee('comptes au total');
    }

    /**
     * LES CHIFFRES EXACTS RESTENT.
     *
     * Un anneau donne la proportion, pas la valeur. Remplacer la liste par le graphique
     * aurait retiré au super-administrateur la seule information qu'il vient chercher.
     */
    public function test_la_liste_chiffree_reste_a_cote_de_l_anneau(): void
    {
        $patron = $this->superAdmin();
        User::factory()->client()->count(4)->create();

        Livewire::actingAs($patron)
            ->test(SuperAdminDashboard::class)
            ->assertSee('Comptes par rôle')
            ->assertSee('au total');
    }

    /**
     * LES DONNÉES PASSENT PAR LE DOM, et le test le vérifie.
     *
     * Une expression imbriquée dans une directive Blade casse la compilation de la vue
     * entière — appris sur le tableau de bord de la société prestataire, où le symptôme
     * remontait quarante lignes plus bas sur une boucle qui n'y était pour rien.
     */
    public function test_les_valeurs_sont_portees_par_des_attributs_de_donnees(): void
    {
        $patron = $this->superAdmin();
        User::factory()->client()->count(2)->create();

        $rendu = Livewire::actingAs($patron)->test(SuperAdminDashboard::class)->html();

        $this->assertStringContainsString('data-valeurs=', $rendu);
        $this->assertStringContainsString('data-libelles=', $rendu);
    }

    /** TÉMOIN — le compteur total du bandeau suit la population réelle. */
    public function test_le_total_suit_la_population(): void
    {
        $patron = $this->superAdmin();
        User::factory()->client()->count(6)->create();

        // Le super-administrateur se compte lui aussi : sept comptes en tout.
        Livewire::actingAs($patron)
            ->test(SuperAdminDashboard::class)
            ->assertSee('7');
    }
}
