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
 * CE QUI EST REELLEMENT PARTI, ET CE QUI EN EST REVENU.
 *
 * Les remis, ouvertures, clics et rebonds viennent du point d'entree des retours d'expedition.
 * UN ZERO N'A PAS LE MEME SENS SELON QUE CE POINT EST BRANCHE OU NON : sans clef de signature,
 * « zero ouverture » veut dire « nous l'ignorons », pas « personne n'a ouvert ». L'ecran dit
 * lequel des deux — le taire serait mentir par omission.
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

    /**
     * UN ZERO NE VEUT PAS DIRE LA MEME CHOSE selon que le point d'entree est branche ou non.
     *
     * Sans clef, « zero ouverture » se lit « nous l'ignorons ». L'ecran doit le dire, sinon il
     * ment par omission — et c'est exactement le « Tests 200 » retire de cinq pages ce matin.
     */
    public function test_l_ecran_dit_que_le_point_d_entree_n_est_pas_configure(): void
    {
        config()->set('email_v2.webhooks', ['mailgun' => ['signing_key' => null]]);

        Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)
            ->assertSee('Aucun point d’entrée n’est configuré', false);
    }

    /** TEMOIN — avec une clef, l'ecran dit l'inverse, et les zeros redeviennent des resultats. */
    public function test_temoin_avec_une_clef_l_ecran_dit_le_contraire(): void
    {
        config()->set('email_v2.webhooks', ['mailgun' => ['signing_key' => 'une-clef']]);

        Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)
            ->assertSee('Le point d’entrée est configuré', false)
            ->assertDontSee('Aucun point d’entrée n’est configuré', false);
    }

    /** LES RETOURS COMPTENT VRAIMENT : une ouverture posee sur un envoi remonte au compteur. */
    public function test_une_ouverture_remonte_au_compteur(): void
    {
        $this->envoyer('client@brio.test');

        DB::table('email_messages')->update(['opened_at' => now(), 'status' => 'opened']);

        $reperes = Livewire::actingAs($this->admin())->test(EmailMesureStudio::class)->get('reperes');

        $this->assertSame(1, $reperes['ouverts']);
        $this->assertSame(0, $reperes['cliques'], 'Un clic n’a pas été rapporté : le compteur doit rester à zéro.');
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
