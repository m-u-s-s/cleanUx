<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OutilsAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CENTRE D'OUTILS NE REND PLUS UNE PAGE ENTIERE DANS UNE CARTE.
 *
 * `ProductEmailsCenter` est une page ROUTEE, avec son propre bandeau editorial. Elle etait
 * imbriquee ici : l'ecran entier se rendait deux fois, une fois chez lui et une fois au milieu
 * des outils. Un lien suffit.
 *
 * Et ce lien est filtre sur la CAPACITE, pas sur l'existence de la route : `Route::has()` dit que
 * la porte existe, pas qu'on a la cle — l'administrateur cliquait sinon vers un 403 nu.
 */
class LesOutilsNeDupliquentPlusUnePageTest extends TestCase
{
    use RefreshDatabase;

    /** LE BANDEAU DE LA PAGE E-MAILS n'a plus rien a faire au milieu des outils. */
    public function test_la_page_n_imbrique_plus_le_centre_e_mails(): void
    {
        Livewire::actingAs($this->outilleur())->test(OutilsAdmin::class)
            ->assertDontSee('Centre de communication & suivi qualité', false);
    }

    /**
     * TEMOIN — ce bandeau existe toujours, et reste visible sur la page a laquelle il appartient.
     *
     * Sans lui, le refus ci-dessus passerait au vert sur une phrase mal orthographiee, ou parce
     * que le bandeau aurait ete supprime du produit : il mesurerait alors autre chose.
     */
    public function test_temoin_le_bandeau_reste_sur_la_page_e_mails(): void
    {
        $this->actingAs($this->toutPuissant())
            ->get('/admin/emails')
            ->assertOk()
            ->assertSee('Centre de communication & suivi qualité', false);
    }

    /** LA PAGE VOISINE RESTE ATTEIGNABLE : le lien remplace l'imbrication, il ne la supprime pas. */
    public function test_le_lien_vers_les_e_mails_remplace_l_imbrication(): void
    {
        Livewire::actingAs($this->toutPuissant())->test(OutilsAdmin::class)
            ->assertSee('E-mails produit')
            ->assertSee(route('admin.emails'), false);
    }

    /**
     * LE LIEN SUIT LA CAPACITE, PAS LA ROUTE.
     *
     * Un compte qui porte `manage-platform` sans `manage-communication` ne doit pas voir un lien
     * dont le middleware lui refuserait l'entree.
     */
    public function test_un_lien_dont_la_capacite_manque_ne_s_affiche_pas(): void
    {
        Livewire::actingAs($this->outilleur())->test(OutilsAdmin::class)
            ->assertDontSee('E-mails produit')
            ->assertDontSee('Crédits clients');
    }

    /** TEMOIN — les memes liens apparaissent des que les capacites sont la. */
    public function test_temoin_les_liens_apparaissent_avec_les_capacites(): void
    {
        Livewire::actingAs($this->toutPuissant())->test(OutilsAdmin::class)
            ->assertSee('E-mails produit')
            ->assertSee('Crédits clients');
    }

    /** LES COMPTES DE LA PLATEFORME remontent en haut de page, la ou on les cherche. */
    public function test_les_reperes_montrent_l_etat_de_la_plateforme(): void
    {
        User::factory()->count(3)->create();

        Livewire::actingAs($this->outilleur())->test(OutilsAdmin::class)
            ->assertSee('Comptes')
            ->assertSee('Rendez-vous')
            ->assertSee('Journaux');
    }

    /**
     * LA CAPACITE GARDE AUSSI LE COMPOSANT : `module_gate` pose `manage-platform` sur la route,
     * mais `/livewire/update` ne rejoue aucun middleware.
     */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(OutilsAdmin::class)
            ->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit ; sans lui le refus mesurerait une panne. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        Livewire::actingAs($this->outilleur())->test(OutilsAdmin::class)
            ->assertOk();
    }

    /** Un compte qui n'a QUE la capacite de cette page. */
    private function outilleur(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-platform'],
        ]);
    }

    /** Un super-administrateur : toutes les capacites, sans en lister aucune. */
    private function toutPuissant(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }
}
