<?php

namespace Tests\Feature\Email;

use App\Livewire\Admin\EmailMesureStudio;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\EnvoiDEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * CE QUI EST REELLEMENT PARTI, ET CE QU'ON NE MESURE PAS ENCORE.
 *
 * Le tableau ne montre que ce que la base sait. LES OUVERTURES ET LES CLICS N'Y FIGURENT PAS :
 * les colonnes existent sur `email_messages`, `EmailWebhookEvent` existe aussi, mais AUCUNE route
 * ni aucun ecrivain ne les alimente. Un taux d'ouverture serait un zero permanent presente comme
 * un resultat — exactement le chiffre faux qu'un tableau de bord ne doit jamais produire.
 */
class LaMesureDesEnvoisTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_reperes_comptent_les_envois_de_la_fenetre(): void
    {
        $this->envoyer('un@brio.test');
        $this->envoyer('deux@brio.test');
        $this->envoyer('deux@brio.test');

        $composant = Livewire::actingAs($this->admin())->test(EmailMesureStudio::class);
        $reperes = $composant->get('reperes');

        $this->assertSame(3, $reperes['envoyes']);
        $this->assertSame(2, $reperes['destinataires'], 'Le meme destinataire ne compte qu une fois.');
        $this->assertSame(1, $reperes['gabarits']);
    }

    /** LA FENETRE BORNE VRAIMENT : un envoi ancien n'entre pas dans le compte. */
    public function test_un_envoi_hors_fenetre_ne_compte_pas(): void
    {
        $this->envoyer('ancien@brio.test');

        DB::table('email_messages')->update(['created_at' => now()->subDays(120)]);

        $composant = Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)->set('fenetre', 30);

        $this->assertSame(0, $composant->get('reperes')['envoyes']);
    }

    /** TEMOIN — la meme ligne rentre dans le compte des qu'on elargit la fenetre. */
    public function test_temoin_une_fenetre_plus_large_le_reprend(): void
    {
        $this->envoyer('ancien@brio.test');

        DB::table('email_messages')->update(['created_at' => now()->subDays(120)]);

        $composant = Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)->set('fenetre', 365);

        $this->assertSame(1, $composant->get('reperes')['envoyes']);
    }

    public function test_le_tableau_par_gabarit_nomme_le_gabarit(): void
    {
        $this->envoyer('client@brio.test');

        Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)
            ->assertSee('Rendez-vous confirmé')
            ->assertSee('booking_confirmed');
    }

    /**
     * UN ENVOI SURVIT A LA SUPPRESSION DE SON GABARIT.
     *
     * Le code reste alors la seule identite : afficher un nom vide effacerait la trace.
     */
    public function test_un_envoi_orphelin_garde_son_code_pour_identite(): void
    {
        $this->envoyer('client@brio.test');

        EmailTemplate::query()->where('code', 'booking_confirmed')->delete();

        Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)
            ->assertSee('booking_confirmed');
    }

    /** LES ANGLES MORTS SE DISENT A VOIX HAUTE, plutot que de s'afficher a zero. */
    public function test_l_ecran_nomme_ce_qu_il_ne_mesure_pas(): void
    {
        Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)
            ->assertSee('Ce qui n’est pas encore mesuré', false)
            ->assertSee('Ouvertures et clics', false);
    }

    /**
     * TEMOIN — aucun taux d'ouverture n'est affiche.
     *
     * C'est le controle qui empeche un futur lot d'ajouter un compteur toujours nul en croyant
     * enrichir l'ecran : tant qu'aucun webhook n'ecrit, un tel chiffre ment.
     */
    public function test_temoin_aucun_taux_d_ouverture_n_est_affiche(): void
    {
        $rendu = Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)->html();

        $this->assertStringNotContainsString('Taux d’ouverture', $rendu);
        $this->assertStringNotContainsString('Taux de clic', $rendu);
    }

    /** LA CAPACITE GARDE AUSSI CE COMPOSANT — il est imbrique, donc atteignable directement. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(EmailMesureStudio::class)->assertForbidden();
    }

    /** TEMOIN — la meme visite avec la capacite aboutit. */
    public function test_temoin_un_administrateur_avec_la_capacite_entre(): void
    {
        $avecCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-communication'],
        ]);

        Livewire::actingAs($avecCapacite)->test(EmailMesureStudio::class)->assertOk();
    }

    private function admin(): User
    {
        return User::factory()->adminComplet()->create([
            'access_scope' => 'all',
            'is_active' => true,
        ]);
    }

    private function envoyer(string $destinataire): void
    {
        app(EnvoiDEmail::class)->envoyer(
            EmailTemplate::query()->where('code', 'booking_confirmed')->firstOrFail(),
            $destinataire,
        );
    }
}
