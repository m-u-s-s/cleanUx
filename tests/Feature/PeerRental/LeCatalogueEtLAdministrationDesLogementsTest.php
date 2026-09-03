<?php

namespace Tests\Feature\PeerRental;

use App\Livewire\PeerRental\PeerAdminCenter;
use App\Livewire\PeerRental\PeerStayCatalogue;
use App\Models\PeerStay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CATALOGUE DES LOGEMENTS, ET LEUR ADMINISTRATION.
 *
 * Le catalogue ne montre que ce qui est reservable : les dates filtrent VRAIMENT, plutot que
 * d'afficher une annonce prise puis de la refuser au moment de payer.
 *
 * L'administration AGIT : elle publie, refuse avec un motif, retire du catalogue. Un ecran qui ne
 * ferait que lister n'aurait aucune raison d'exister a cote de la base.
 */
class LeCatalogueEtLAdministrationDesLogementsTest extends TestCase
{
    use RefreshDatabase;

    // ── Le catalogue ───────────────────────────────────────────────────────

    /** SEULES LES ANNONCES PUBLIEES SONT VISIBLES. */
    public function test_le_catalogue_ne_montre_que_les_annonces_publiees(): void
    {
        $publie = PeerStay::factory()->publiee()->create(['title' => 'Studio publié']);
        PeerStay::factory()->create(['title' => 'Brouillon caché']);

        Livewire::test(PeerStayCatalogue::class)
            ->assertSee($publie->title)
            ->assertDontSee('Brouillon caché');
    }

    /** LA CAPACITE FILTRE TOUJOURS : proposer un studio pour deux a six personnes fait perdre du temps. */
    public function test_un_logement_trop_petit_n_apparait_pas(): void
    {
        PeerStay::factory()->publiee()->create(['title' => 'Studio deux places', 'max_guests' => 2]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('voyageurs', 6)
            ->assertDontSee('Studio deux places');
    }

    /** TEMOIN — le meme logement reapparait des que la demande redescend a sa capacite. */
    public function test_temoin_le_meme_logement_reapparait_pour_deux_voyageurs(): void
    {
        PeerStay::factory()->publiee()->create(['title' => 'Studio deux places', 'max_guests' => 2]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('voyageurs', 2)
            ->assertSee('Studio deux places');
    }

    /**
     * TOUS LES EQUIPEMENTS DEMANDES, PAS AU MOINS UN.
     *
     * Cocher « lave-linge » et « parking » veut dire les deux ; l'inverse rendrait le filtre inutile.
     */
    public function test_le_filtre_d_equipements_exige_les_deux(): void
    {
        PeerStay::factory()->publiee()->create([
            'title' => 'Avec wifi seulement',
            'amenities' => ['wifi'],
        ]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('equipements', ['wifi', 'parking'])
            ->assertDontSee('Avec wifi seulement');
    }

    /** TEMOIN — un logement qui porte les deux equipements passe bien le filtre. */
    public function test_temoin_un_logement_qui_a_les_deux_passe(): void
    {
        PeerStay::factory()->publiee()->create([
            'title' => 'Avec wifi et parking',
            'amenities' => ['wifi', 'parking', 'cuisine'],
        ]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('equipements', ['wifi', 'parking'])
            ->assertSee('Avec wifi et parking');
    }

    /**
     * LES DATES FILTRENT VRAIMENT.
     *
     * Beaucoup de catalogues affichent tout puis refusent a la reservation : le voyageur clique
     * pour rien, et sa confiance dans le catalogue avec.
     */
    public function test_une_periode_fermee_retire_le_logement_du_catalogue(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['title' => 'Maison fermée en juillet', 'min_nights' => 1]);

        $logement->indisponibilites()->create([
            'starts_on' => now()->addDays(10)->toDateString(),
            'ends_on' => now()->addDays(20)->toDateString(),
            'kind' => 'blocked',
        ]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('debut', now()->addDays(12)->toDateString())
            ->set('fin', now()->addDays(14)->toDateString())
            ->assertDontSee('Maison fermée en juillet');
    }

    /** TEMOIN — hors de la periode fermee, le meme logement revient. */
    public function test_temoin_hors_periode_fermee_le_logement_revient(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['title' => 'Maison fermée en juillet', 'min_nights' => 1]);

        $logement->indisponibilites()->create([
            'starts_on' => now()->addDays(10)->toDateString(),
            'ends_on' => now()->addDays(20)->toDateString(),
            'kind' => 'blocked',
        ]);

        Livewire::test(PeerStayCatalogue::class)
            ->set('debut', now()->addDays(30)->toDateString())
            ->set('fin', now()->addDays(32)->toDateString())
            ->assertSee('Maison fermée en juillet');
    }

    // ── L'administration ───────────────────────────────────────────────────

    /** L'ADMINISTRATION PUBLIE — et la publication porte la trace de qui l'a decidee. */
    public function test_l_administration_publie_un_logement_et_laisse_sa_trace(): void
    {
        $logement = PeerStay::factory()->enRevue()->create();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(PeerAdminCenter::class)
            ->call('publierLeLogement', $logement->id);

        $logement->refresh();

        $this->assertSame(PeerStay::STATUT_PUBLIE, $logement->status);
        $this->assertNotNull($logement->published_at);
        $this->assertSame($admin->id, (int) $logement->reviewed_by);
    }

    /**
     * UN REFUS PORTE TOUJOURS UN MOTIF.
     *
     * Sans explication ecrite, il n'est ni corrigeable par le proprietaire, ni defendable six
     * mois plus tard.
     */
    public function test_un_refus_porte_toujours_un_motif(): void
    {
        $logement = PeerStay::factory()->enRevue()->create();

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->set('motifRefus', 'Photos illisibles')
            ->call('refuserLeLogement', $logement->id);

        $logement->refresh();

        $this->assertSame(PeerStay::STATUT_REFUSE, $logement->status);
        $this->assertSame('Photos illisibles', $logement->rejection_reason);
    }

    /** TEMOIN — meme sans saisie, un motif par defaut est ecrit : jamais de refus muet. */
    public function test_temoin_un_refus_sans_saisie_porte_quand_meme_un_motif(): void
    {
        $logement = PeerStay::factory()->enRevue()->create();

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->call('refuserLeLogement', $logement->id);

        $this->assertNotEmpty($logement->fresh()->rejection_reason);
    }

    /** RETIRER UNE ANNONCE EN LIGNE sans attendre un signalement. */
    public function test_l_administration_retire_un_logement_du_catalogue(): void
    {
        $logement = PeerStay::factory()->publiee()->create();

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->call('suspendreLeLogement', $logement->id);

        $this->assertSame(PeerStay::STATUT_SUSPENDU, $logement->fresh()->status);
    }

    /** LES CHIFFRES DISTINGUENT LES DEUX BIENS : un total melange ne pilote rien. */
    public function test_les_chiffres_distinguent_logements_et_vehicules(): void
    {
        PeerStay::factory()->publiee()->count(2)->create();
        PeerStay::factory()->enRevue()->create();

        $chiffres = Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)->instance()->chiffres;

        $this->assertSame(2, $chiffres['logements']);
        $this->assertSame(1, $chiffres['logements_en_attente']);
        $this->assertArrayHasKey('vehicules', $chiffres);
    }

    /** LA CAPACITE GARDE L'ECRAN : la meme condition que la case du registre. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        $sansCapacite = User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-calendar'],
        ]);

        Livewire::actingAs($sansCapacite)->test(PeerAdminCenter::class)->assertForbidden();
    }

    /**
     * RETIRER N EST PAS SANS RETOUR.
     *
     * Une annonce suspendue ne figurait dans aucune des deux listes : seul un acces direct a la
     * base pouvait la remettre en ligne.
     */
    public function test_un_logement_retire_reste_visible_et_se_remet_en_ligne(): void
    {
        $logement = PeerStay::factory()->create([
            'status' => PeerStay::STATUT_SUSPENDU,
            'title' => 'Loft retiré',
        ]);

        $ecran = Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->set('onglet', 'logements')
            ->assertSee('Loft retiré');

        $ecran->call('publierLeLogement', $logement->id);

        $this->assertSame(PeerStay::STATUT_PUBLIE, $logement->fresh()->status);
    }

    /** UN REFUS SE REPREND AUSSI, et son motif s efface avec lui. */
    public function test_un_logement_refuse_se_remet_en_ligne_sans_son_motif(): void
    {
        $logement = PeerStay::factory()->create([
            'status' => PeerStay::STATUT_REFUSE,
            'rejection_reason' => 'Photos illisibles',
        ]);

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->call('publierLeLogement', $logement->id);

        $this->assertNull($logement->fresh()->rejection_reason);
    }

    /** LA RECHERCHE PORTE SUR LES TROIS LISTES A LA FOIS. */
    public function test_la_recherche_ecarte_les_logements_qui_ne_correspondent_pas(): void
    {
        PeerStay::factory()->publiee()->create(['title' => 'Loft du canal', 'city' => 'Bruxelles']);
        PeerStay::factory()->publiee()->create(['title' => 'Chalet des cimes', 'city' => 'Liège']);

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->set('onglet', 'logements')
            ->set('rechercheLogement', 'Chalet')
            ->assertSee('Chalet des cimes')
            ->assertDontSee('Loft du canal');
    }

    /** TEMOIN — sans terme de recherche, les deux annonces sont la. */
    public function test_temoin_sans_recherche_les_deux_logements_sont_la(): void
    {
        PeerStay::factory()->publiee()->create(['title' => 'Loft du canal', 'city' => 'Bruxelles']);
        PeerStay::factory()->publiee()->create(['title' => 'Chalet des cimes', 'city' => 'Liège']);

        Livewire::actingAs($this->admin())->test(PeerAdminCenter::class)
            ->set('onglet', 'logements')
            ->assertSee('Chalet des cimes')
            ->assertSee('Loft du canal');
    }

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'permissions' => ['manage-peer-rentals'],
        ]);
    }
}
