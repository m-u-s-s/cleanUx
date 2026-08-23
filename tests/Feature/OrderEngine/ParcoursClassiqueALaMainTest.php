<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\ClientPlace;
use App\Models\Mission;
use App\Models\Trade;
use App\Models\User;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\Domain\BookingStatus;
use Database\Seeders\DemoPlatformSeeder;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** CE QUE LE PARCOURS CLASSIQUE FAISAIT VIVRE — relevé en le déroulant à la main. */
class ParcoursClassiqueALaMainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    private function metier(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** CHOISIR UN MÉTIER L'INSCRIT AU PANIER. Il n'y était inscrit qu'à la première réponse. */
    public function test_choisir_un_metier_cree_la_ligne_de_panier(): void
    {
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->metier()->id);

        $panier = $composant->instance()->draft();

        $this->assertSame(
            1,
            $panier->items()->count(),
            'L’écran annonçait un service et son prix pendant que le panier restait vide.'
        );
    }

    /** Revenir sur le même métier ne crée pas de doublon. */
    public function test_revenir_sur_le_meme_metier_ne_double_pas_la_ligne(): void
    {
        $client = User::factory()->client()->create();
        $this->actingAs($client);

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectTrade', $this->metier()->id)
            ->call('selectTrade', $this->metier()->id);

        $this->assertSame(1, $composant->instance()->draft()->items()->count());
    }

    /** UN LIEU ENREGISTRÉ SANS COORDONNÉES EST GÉOCODÉ, PAS SUBI. */
    public function test_un_lieu_enregistre_sans_coordonnees_est_geocode(): void
    {
        $client = User::factory()->client()->create();
        $lieu = ClientPlace::create([
            'user_id' => $client->id,
            'label' => 'Chez moi',
            'address' => 'Bruxelles',
            'postal_code' => '1000',
        ]);

        $this->assertNull($lieu->lat, 'Le témoin de départ : le lieu n’a pas de coordonnées.');

        $this->actingAs($client);
        Livewire::test(OrderJourney::class)->call('choisirLeLieu', $lieu->id);

        $this->assertNotNull(
            $lieu->fresh()->lat,
            'Le lieu doit être situé — et réparé pour la fois suivante.'
        );
    }

    /** LA MISSION DURE LE TEMPS ANNONCÉ, pas zéro minute. */
    public function test_la_mission_ne_dure_pas_zero_minute(): void
    {
        $client = User::factory()->client()->create();

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'trade_id' => $this->metier()->id,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '10:00:00',
        ]);

        $mission = app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($booking->fresh());

        $this->assertNotNull($mission->planned_end_at);
        $this->assertGreaterThan(
            0,
            $mission->planned_start_at->diffInMinutes($mission->planned_end_at),
            'Une intervention de zéro minute ne chevauche rien et ne protège aucun créneau.'
        );
    }

    /** LE LIBELLÉ DU SERVICE N'EST PAS DÉFORMÉ. `headline()` rend lisible un identifiant technique. */
    public function test_le_libelle_du_service_n_est_pas_deforme(): void
    {
        $booking = new Booking(['pricing_snapshot' => ['service_name' => 'Nettoyage à domicile']]);

        $this->assertSame('Nettoyage à domicile', $booking->service_display_name);
    }

    /** LE TÉMOIN : un identifiant technique, lui, reste rendu lisible. */
    public function test_un_identifiant_technique_reste_mis_en_forme(): void
    {
        $booking = new Booking(['pricing_snapshot' => ['service_name' => 'cleaning_residential']]);

        $this->assertSame('Cleaning Residential', $booking->service_display_name);
    }

    /** LE SEEDER PEUPLE LA TABLE QUE LA PLATEFORME LIT. */
    public function test_le_seeder_de_demonstration_peuple_les_creneaux_lus_par_la_plateforme(): void
    {
        $this->assertSame(0, AvailabilitySlot::count(), 'Témoin de départ.');

        $this->seed(DemoPlatformSeeder::class);

        $this->assertGreaterThan(
            0,
            AvailabilitySlot::count(),
            'Sans une seule ligne ici, aucun rendez-vous n’est réservable, nulle part.'
        );
    }
}
