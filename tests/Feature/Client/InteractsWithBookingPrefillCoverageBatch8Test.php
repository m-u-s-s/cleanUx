<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Exercises the booking pre-fill concern driven through the public booking
 * Livewire host (PrendreRendezVous::mount → hydrateFromQuery). Covers the
 * "prefill=last", "source_rdv", address-query and premium-employe query
 * branches as well as the full RendezVous → form-state mapping.
 */
class InteractsWithBookingPrefillCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    private function premiumClient(): User
    {
        return User::factory()->premiumClient()->create();
    }

    #[Test]
    public function prefill_last_hydrates_the_form_from_the_clients_most_recent_booking(): void
    {
        $client = $this->premiumClient();

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'type_lieu' => 'maison',
            'surface' => '100_150',
            'frequence' => 'hebdomadaire',
            'options_prestation' => ['vitres', 'four'],
            'zones_specifiques' => ['cuisine'],
            'materiel_specifique' => ['aspirateur', 'balai'],
            'commentaire_client' => 'Sonner deux fois',
            'presence_animaux' => true,
            'acces_parking' => true,
            'priorite' => 'haute',
        ]);

        $this->actingAs($client);

        $component = Livewire::withQueryParams(['prefill' => 'last'])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromLast', true)
            ->assertSet('prefilledFromSource', false)
            ->assertSet('type_lieu', 'maison')
            ->assertSet('surface', '100_150')
            ->assertSet('frequence', 'hebdomadaire')
            ->assertSet('options_prestation', ['vitres', 'four'])
            ->assertSet('zones_specifiques', ['cuisine'])
            ->assertSet('materiel_specifique', 'aspirateur, balai')
            ->assertSet('commentaire_client', 'Sonner deux fois')
            ->assertSet('presence_animaux', true)
            ->assertSet('acces_parking', true)
            ->assertSet('priorite', 'haute')
            ->assertSet('step', 4);

        $this->assertTrue($component->instance()->getHasPrefillProperty());
        $this->assertSame($booking->telephone_client, $component->get('telephone_client'));
    }

    #[Test]
    public function prefill_last_does_nothing_when_the_client_has_no_bookings(): void
    {
        $client = $this->premiumClient();

        $this->actingAs($client);

        $component = Livewire::withQueryParams(['prefill' => 'last'])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromLast', false);

        $this->assertFalse($component->instance()->getHasPrefillProperty());
    }

    #[Test]
    public function source_rdv_hydrates_from_a_booking_owned_by_the_client(): void
    {
        $client = $this->premiumClient();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'type_lieu' => 'bureaux',
        ]);

        $this->actingAs($client);

        Livewire::withQueryParams(['source_rdv' => $booking->id])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromSource', true)
            ->assertSet('prefilledFromLast', false)
            ->assertSet('type_lieu', 'bureaux')
            ->assertSet('step', 4);
    }

    #[Test]
    public function source_rdv_is_ignored_when_the_booking_belongs_to_another_client(): void
    {
        $client = $this->premiumClient();
        $foreign = Booking::factory()->create();

        $this->actingAs($client);

        Livewire::withQueryParams(['source_rdv' => $foreign->id])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromSource', false);
    }

    #[Test]
    public function address_query_parameters_prefill_the_address_fields_and_advance_the_step(): void
    {
        $client = $this->premiumClient();

        $this->actingAs($client);

        $component = Livewire::withQueryParams([
            'adresse' => '12 Rue de la Loi',
            'ville' => 'Bruxelles',
            'code_postal' => '1000',
        ])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromAddress', true)
            ->assertSet('adresse', '12 Rue de la Loi')
            ->assertSet('ville', 'Bruxelles')
            ->assertSet('postal_code_input', '1000');

        $this->assertGreaterThanOrEqual(3, (int) $component->get('step'));
        $this->assertTrue($component->instance()->getHasPrefillProperty());
    }

    #[Test]
    public function premium_client_gets_the_requested_employe_prefilled_from_the_query(): void
    {
        $client = $this->premiumClient();
        $employe = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::factory()->create([
            'user_id' => $employe->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $this->actingAs($client);

        Livewire::withQueryParams(['employe' => $employe->id])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('employe_id', $employe->id);
    }

    #[Test]
    public function non_premium_client_does_not_get_the_requested_employe_prefilled(): void
    {
        $client = User::factory()->client()->create();
        $employe = User::factory()->employe()->create();

        $this->actingAs($client);

        Livewire::withQueryParams(['employe' => $employe->id])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('employe_id', null);
    }

    #[Test]
    public function prefill_from_an_organization_booking_applies_the_site_context(): void
    {
        $booking = Booking::factory()->entreprise()->create();
        $client = $booking->client;

        $this->actingAs($client);

        Livewire::withQueryParams(['source_rdv' => $booking->id])
            ->test(PrendreRendezVous::class)
            ->assertOk()
            ->assertSet('prefilledFromSource', true)
            ->assertSet('organization_site_id', $booking->organization_site_id);
    }
}
