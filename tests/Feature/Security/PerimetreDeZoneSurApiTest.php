<?php

namespace Tests\Feature\Security;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * LE PÉRIMÈTRE DE ZONE DOIT VALOIR DES DEUX CÔTÉS.
 *
 * Un administrateur peut être limité à une zone : `AdminScope` borne alors ses
 * requêtes, et six écrans Livewire l'appliquent. AUCUN contrôleur d'API ne
 * l'appelait — le tableau de bord mobile lui montrait donc les chiffres de toute
 * la plateforme, pendant que le web lui montrait ceux de sa zone.
 *
 * Personne n'est zoné en base aujourd'hui, ce qui explique que rien ne l'ait
 * révélé. Le trou n'en est pas moins réel : il s'ouvrira au premier
 * administrateur régional.
 */
class PerimetreDeZoneSurApiTest extends TestCase
{
    use RefreshDatabase;

    private function zone(string $nom): ServiceZone
    {
        return ServiceZone::factory()->create(['name' => $nom]);
    }

    private function admin(?ServiceZone $zone): User
    {
        return User::factory()->adminComplet()->create([
            'two_factor_confirmed_at' => now(),
            'access_scope' => $zone ? 'zone' : 'all',
            'managed_service_zone_id' => $zone?->id,
        ]);
    }

    private function reservationDansLaZone(ServiceZone $zone): Booking
    {
        return Booking::factory()->create([
            'service_zone_id' => $zone->id,
            'scheduled_date' => today(),
            'status' => 'en_attente',
        ]);
    }

    /** TÉMOIN POSITIF — l'administrateur global compte toute la plateforme. */
    public function test_temoin_l_administrateur_global_voit_tout(): void
    {
        $nord = $this->zone('Nord');
        $sud = $this->zone('Sud');
        $this->reservationDansLaZone($nord);
        $this->reservationDansLaZone($sud);

        Sanctum::actingAs($this->admin(null), ['*']);

        $reponse = $this->getJson('/api/admin/overview')->assertOk();

        $this->assertSame(2, $this->compteur($reponse->json(), 'bookings_today'),
            "L'administrateur sans périmètre doit voir les deux réservations");
    }

    /** PORTÉE — l'administrateur régional ne compte que sa zone. */
    public function test_l_administrateur_de_zone_ne_compte_que_la_sienne(): void
    {
        $nord = $this->zone('Nord');
        $sud = $this->zone('Sud');
        $this->reservationDansLaZone($nord);
        $this->reservationDansLaZone($sud);

        Sanctum::actingAs($this->admin($nord), ['*']);

        $reponse = $this->getJson('/api/admin/overview')->assertOk();

        $this->assertSame(1, $this->compteur($reponse->json(), 'bookings_today'),
            'Un administrateur de zone ne doit pas compter les réservations des autres zones');
    }

    /** @param array<string,mixed> $charge */
    private function compteur(array $charge, string $cle): ?int
    {
        foreach ($charge['kpis'] ?? [] as $kpi) {
            if (($kpi['key'] ?? null) === $cle) {
                return (int) $kpi['value'];
            }
        }

        return null;
    }
}
