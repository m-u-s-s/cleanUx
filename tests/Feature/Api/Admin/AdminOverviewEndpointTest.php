<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Les indicateurs d'accueil de la console d'administration mobile.
 *
 * Ils sont volontairement peu nombreux et tous comptables : un accueil qui affiche sept nombres
 * exacts vaut mieux qu'un tableau de bord riche dont on ne sait pas ce qu'il mesure.
 */
class AdminOverviewEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['*']);
    }

    public function test_l_accueil_rend_sept_indicateurs_chiffres(): void
    {
        $this->actingAsAdmin();

        $res = $this->getJson('/api/admin/overview')->assertOk();

        $res->assertJsonPath('ok', true);
        $kpis = $res->json('kpis');
        $this->assertCount(7, $kpis);

        foreach ($kpis as $kpi) {
            $this->assertNotEmpty($kpi['key']);
            $this->assertNotEmpty($kpi['label']);
            $this->assertIsInt($kpi['value']);
            $this->assertArrayHasKey('icon', $kpi);

            // Le contrôleur rattrape les erreurs pour qu'une table absente ne fasse pas tomber
            // l'accueil entier. Sans cette assertion, une requête cassée rendrait 0 et le test
            // passerait : c'est exactement le genre de vert qui ne prouve rien.
            $this->assertTrue($kpi['available'], "L'indicateur {$kpi['key']} n'a pas pu être mesuré.");
        }
    }

    public function test_les_cles_d_indicateurs_sont_stables(): void
    {
        $this->actingAsAdmin();

        // L'application mobile route et intitule d'après ces clés. Les renommer casserait des
        // écrans sans qu'aucun typage ne le signale.
        $this->assertSame(
            ['users', 'bookings_pending', 'bookings_today', 'missions_active', 'claims_open', 'kyc_pending', 'providers_pending'],
            array_column($this->getJson('/api/admin/overview')->json('kpis'), 'key'),
        );
    }

    public function test_le_compte_d_utilisateurs_est_exact(): void
    {
        $this->actingAsAdmin();
        User::factory()->count(3)->create();

        $users = collect($this->getJson('/api/admin/overview')->json('kpis'))->firstWhere('key', 'users');

        $this->assertSame(User::count(), $users['value']);
    }

    public function test_les_reservations_en_attente_sont_comptees_dans_les_deux_langues(): void
    {
        $this->actingAsAdmin();

        // La colonne `status` porte historiquement des valeurs françaises ET anglaises. Compter
        // sur une seule des deux formes donnerait un chiffre faux la moitié du temps.
        Booking::factory()->create(['status' => BookingStatus::EN_ATTENTE]);
        Booking::factory()->create(['status' => 'pending']);
        Booking::factory()->create(['status' => BookingStatus::TERMINE]);

        $kpi = collect($this->getJson('/api/admin/overview')->json('kpis'))
            ->firstWhere('key', 'bookings_pending');

        $this->assertSame(2, $kpi['value']);
    }

    public function test_un_non_admin_est_refuse(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'client']), ['*']);

        $this->getJson('/api/admin/overview')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_not_admin');
    }
}
