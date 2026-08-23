<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Les indicateurs d'accueil de la console d'administration mobile. */
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

        // TOUS LES INDICATEURS FAUTIFS, PAS LE PREMIER.
        $fautifs = [];

        foreach ($kpis as $i => $kpi) {
            $nom = $kpi['key'] ?? "indicateur #{$i}";

            foreach (['key', 'label'] as $clef) {
                if (blank($kpi[$clef] ?? null)) {
                    $fautifs[] = "{$nom} : « {$clef} » vide";
                }
            }

            if (! is_int($kpi['value'] ?? null)) {
                $fautifs[] = "{$nom} : valeur ".var_export($kpi['value'] ?? null, true).' au lieu d un entier';
            }

            if (! array_key_exists('icon', $kpi)) {
                $fautifs[] = "{$nom} : pas d icone";
            }

            if (! ($kpi['available'] ?? false)) {
                $fautifs[] = "{$nom} : n a pas pu etre mesure";
            }
        }

        $this->assertSame([], $fautifs, 'Ces indicateurs de l accueil sont inexploitables.');
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
