<?php

namespace Tests\Feature\Admin;

use App\Livewire\AdminDashboard;
use App\Models\Booking;
use App\Models\User;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** La section « Plateforme » du tableau de bord — ex-page `/admin/home`. */
class SantePlateformeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tendance_sur_sept_jours_tient_en_une_seule_requete(): void
    {
        Booking::factory()->count(2)->create(['created_at' => now()]);
        Booking::factory()->create(['created_at' => now()->subDays(2)]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $tendance = (new AdminDashboard)->tendanceDesReservations();
        $requetes = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(7, $tendance);
        $this->assertSame(2, $tendance[6]['count'], 'aujourd’hui doit compter 2 réservations');
        $this->assertSame(1, $tendance[4]['count'], 'il y a 2 jours doit compter 1 réservation');

        $this->assertLessThanOrEqual(1, count($requetes), 'une seule requête groupée, pas sept comptages');
    }

    public function test_l_ancienne_url_conduit_au_tableau_de_bord(): void
    {
        $reponse = $this->actingAs($this->prendreLeSiege())->get('/admin/home');

        $reponse->assertRedirect('/admin/dashboard');
    }

    public function test_le_tableau_de_bord_montre_la_sante_de_la_plateforme(): void
    {
        Livewire::actingAs($this->prendreLeSiege())
            ->test(AdminDashboard::class)
            ->assertOk()
            ->assertSee('Santé de la plateforme')
            ->assertSee('Prestataires en ligne')
            ->assertSee('Versements en attente')
            ->assertSee('Webhooks échoués (24h)')
            ->assertSee('Litiges en cours')
            ->assertSee('Dernières réservations');
    }

    public function test_le_ca_mensuel_suit_le_meme_perimetre_que_les_rdv(): void
    {
        Booking::factory()->create(['date' => now()->startOfMonth(), 'devis_estime' => 120.0]);
        Booking::factory()->create(['date' => now()->startOfMonth(), 'devis_estime' => 80.0]);
        // L'annee derniere : elle ne doit PAS retomber dans le meme mois.
        Booking::factory()->create(['date' => now()->subYear()->startOfMonth(), 'devis_estime' => 999.0]);

        $composant = Livewire::actingAs($this->prendreLeSiege())->test(AdminDashboard::class);

        $mois = (int) now()->month;
        $ca = $composant->get('caMensuel');

        $this->assertCount(12, $ca);
        $this->assertSame(200.0, (float) $ca[$mois - 1], 'le CA du mois doit valoir 120 + 80, sans l’année passée');

        // Temoin positif : les RDV du meme mois sont bien comptes, sans celui de l'an dernier.
        $this->assertSame(2, (int) $composant->get('statsMensuelles')[$mois - 1]);
    }

    public function test_les_totaux_d_argent_de_l_ancien_onglet_sont_la(): void
    {
        Livewire::actingAs($this->prendreLeSiege())
            ->test(AdminDashboard::class)
            ->assertSee('CA total')
            ->assertSee('Marge plateforme')
            ->assertSee('Note moyenne');
    }

    public function test_une_reservation_annulee_ne_compte_pas_dans_le_ca(): void
    {
        Booking::factory()->create(['devis_estime' => 200.0, 'status' => 'confirme']);
        Booking::factory()->create(['devis_estime' => 87.75, 'status' => 'annule']);

        $apercu = app(AdminAnalyticsService::class)->overview();

        $this->assertSame(200.0, $apercu['total_revenue'], 'l’annulation ne doit pas gonfler le CA');
        $this->assertSame(1, $apercu['missions_count'], 'ni le compte de missions');

        // Temoin positif : sans le statut d'annulation, la meme reservation compte bien.
        Booking::factory()->create(['devis_estime' => 50.0, 'status' => 'confirme']);
        $this->assertSame(250.0, app(AdminAnalyticsService::class)->overview()['total_revenue']);
    }

    public function test_la_charge_terrain_ne_liste_que_les_employes_charges(): void
    {
        $charge = User::factory()->create(['role' => 'employe', 'is_active' => true]);
        $oisif = User::factory()->create(['role' => 'employe', 'is_active' => true]);

        Booking::factory()->create([
            'employe_id' => $charge->id,
            'date' => today(),
            'status' => 'confirme',
            'duree' => 120,
        ]);

        $lignes = Livewire::actingAs($this->prendreLeSiege())
            ->test(AdminDashboard::class)
            ->get('chargeEmployes');

        $identifiants = collect($lignes)->pluck('employe.id')->all();

        $this->assertContains($charge->id, $identifiants, 'un employé chargé doit apparaître');
        $this->assertNotContains($oisif->id, $identifiants, 'un employé sans intervention ne doit pas remplir la carte');
    }

    public function test_la_charge_terrain_tient_en_un_nombre_de_requetes_constant(): void
    {
        // DIX EMPLOYES NE DOIVENT PAS COUTER DIX REQUETES : le tableau de bord se sonde
        // toutes les dix secondes, et cette carte partait en N+1.
        User::factory()->count(10)->create(['role' => 'employe', 'is_active' => true]);

        $composant = Livewire::actingAs($this->prendreLeSiege())->test(AdminDashboard::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $composant->instance()->getChargeEmployesProperty();
        $requetes = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(4, $requetes, "la charge terrain a coûté {$requetes} requêtes");
    }

    public function test_la_reference_de_reservation_s_affiche(): void
    {
        // `reference` n'existe pas sur `bookings` : l'ancienne page rendait une colonne vide.
        Booking::factory()->create(['booking_reference' => 'BRIO-TEST-42']);

        Livewire::actingAs($this->prendreLeSiege())
            ->test(AdminDashboard::class)
            ->assertSee('BRIO-TEST-42');
    }

    public function test_section_fermee_la_sante_n_est_pas_calculee(): void
    {
        $composant = Livewire::actingAs($this->prendreLeSiege())
            ->test(AdminDashboard::class)
            ->call('toggleDashboardSection', 'plateforme');

        $composant->assertSet('visibleDashboardSections.plateforme', false)
            ->assertDontSee('Prestataires en ligne');

        // Temoin positif : rouverte, la section revient — sans quoi ce test mesurerait une panne.
        $composant->call('toggleDashboardSection', 'plateforme')
            ->assertSet('visibleDashboardSections.plateforme', true)
            ->assertSee('Prestataires en ligne');
    }
}
