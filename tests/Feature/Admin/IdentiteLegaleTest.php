<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\IdentiteLegale;
use App\Models\Parametre;
use App\Models\User;
use App\Support\Platform\PorteDuSiege;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/** Les mentions légales étaient écrites en dur : la page publique s'annonçait « à compléter ». */
class IdentiteLegaleTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<string>  $capacites */
    private function admin(array $capacites): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        PorteDuSiege::ouvrir(fn () => $admin->forceFill([
            'platform_role' => 'admin',
            'permissions' => $capacites,
        ])->save());

        return $admin->refresh();
    }

    public function test_ce_qui_est_enregistre_s_affiche_sur_la_page_publique(): void
    {
        $this->get(route('legal.mentions'))
            ->assertOk()
            ->assertSee('(à compléter)');

        Livewire::actingAs($this->admin(['manage-platform']))
            ->test(IdentiteLegale::class)
            ->set('valeurs.legal_societe', 'SRL Brio, BCE 0123.456.789')
            ->set('valeurs.legal_siege_social', 'Rue du Test 1, 1000 Bruxelles')
            ->set('valeurs.legal_email_contact', 'legal@brio.test')
            ->set('valeurs.legal_directeur_publication', 'Camille Dupont')
            ->set('valeurs.legal_hebergeur', 'OVH SAS, 2 rue Kellermann, 59100 Roubaix')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->get(route('legal.mentions'))
            ->assertOk()
            ->assertSee('SRL Brio, BCE 0123.456.789')
            ->assertSee('Rue du Test 1, 1000 Bruxelles')
            ->assertSee('legal@brio.test')
            ->assertSee('Camille Dupont')
            ->assertSee('OVH SAS, 2 rue Kellermann, 59100 Roubaix')
            ->assertDontSee('(à compléter)');
    }

    public function test_un_email_de_contact_invalide_est_refuse(): void
    {
        $admin = $this->admin(['manage-platform']);

        Livewire::actingAs($admin)
            ->test(IdentiteLegale::class)
            ->set('valeurs.legal_email_contact', 'pas-une-adresse')
            ->call('enregistrer')
            ->assertHasErrors(['valeurs.legal_email_contact' => 'email']);

        $this->assertSame('', (string) Parametre::getValeur('legal_email_contact', ''),
            'Un refus de validation ne doit rien écrire.');

        // TEMOIN : sans ce contrôle, le refus ci-dessus passerait au vert sur une panne du chemin.
        Livewire::actingAs($admin)
            ->test(IdentiteLegale::class)
            ->set('valeurs.legal_email_contact', 'legal@brio.test')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame('legal@brio.test', (string) Parametre::getValeur('legal_email_contact', ''));
    }

    public function test_la_console_mobile_corrige_une_mention_mais_ne_la_supprime_pas(): void
    {
        Parametre::setValeur('legal_directeur_publication', 'Camille Dupont');
        $ligne = Parametre::where('cle', 'legal_directeur_publication')->firstOrFail();

        Sanctum::actingAs(User::factory()->adminComplet()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);

        // TEMOIN : le meme geste que le web passe bien par la console mobile.
        $this->postJson("/api/admin/console/identite-legale/{$ligne->id}/actions/modifier", [
            'valeur' => 'Alex Martin',
        ])->assertOk();

        $this->assertSame('Alex Martin', (string) Parametre::getValeur('legal_directeur_publication', ''));

        // Effacer la ligne renverrait « (a completer) » sur la page publique, sans rien dire.
        $this->deleteJson("/api/admin/console/identite-legale/{$ligne->id}")->assertStatus(409);

        $this->assertSame('Alex Martin', (string) Parametre::getValeur('legal_directeur_publication', ''));
    }

    public function test_un_admin_sans_la_capacite_n_atteint_pas_la_page(): void
    {
        $this->actingAs($this->admin(['manage-bookings']))
            ->get(route('admin.identite-legale'))
            ->assertForbidden();

        // TEMOIN : la page existe et répond, c'est bien la capacité qui ferme la porte.
        $this->actingAs($this->admin(['manage-platform']))
            ->get(route('admin.identite-legale'))
            ->assertOk();
    }
}
