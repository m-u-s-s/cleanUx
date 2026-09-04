<?php

namespace Tests\Feature\Admin;

use App\Livewire\AdminDashboard;
use App\Models\Booking;
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
