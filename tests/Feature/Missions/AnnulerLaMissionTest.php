<?php

namespace Tests\Feature\Missions;

use App\Livewire\Shared\AnnulerLaMission;
use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Database\Seeders\CancellationPoliciesSeeder;
use Database\Seeders\CancellationQuestionnaireSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** ANNULER — le questionnaire remplace le champ libre, des deux côtés. */
class AnnulerLaMissionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CancellationPoliciesSeeder::class);
        $this->seed(CancellationQuestionnaireSeeder::class);
    }

    private function mission(string $moteur = 'domicile', ?Carbon $demarree = null): Mission
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $prevu = Carbon::now()->addHours(6);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 120.00,
            'estimated_price' => 120.00,
            'scheduled_at' => $prevu,
            'date' => $prevu->toDateString(),
            'heure' => $prevu->format('H:i:s'),
        ] + ($moteur === 'vehicule' ? ['dropoff_lat' => 50.90, 'dropoff_lng' => 4.48] : []));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => $demarree ? MissionStatus::STARTED : MissionStatus::ASSIGNED,
            'actual_start_at' => $demarree,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $mission->fresh('booking');
    }

    public function test_le_client_annule_avec_un_motif_structure(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->client)
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'client'])
            ->call('ouvrir')
            ->set('optionChoisie', 'client_no_longer_needed')
            ->call('confirmer')
            ->assertSet('erreur', null);

        $annulation = BookingCancellationV2::query()->where('booking_id', $mission->booking_id)->firstOrFail();

        $this->assertSame('client_no_longer_needed', $annulation->reason_code);
        // L'INSTANTANÉ : un libellé modifié demain n'altère pas ce qui a été montré hier.
        $this->assertSame(
            'Je n’ai plus besoin de ce service',
            data_get($annulation->metadata, 'questionnaire.option_label'),
        );
    }

    /** L'AIGUILLAGE NE FAIT PAS ANNULER — et il le dit. */
    public function test_un_aiguillage_ne_produit_aucune_annulation(): void
    {
        $mission = $this->mission('domicile');

        Livewire::actingAs($this->prestataire)
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'provider'])
            ->call('ouvrir')
            ->set('optionChoisie', 'provider_scope_mismatch')
            ->assertSee('nouveau devis')
            ->call('confirmer')
            ->assertSet('erreur', 'Cette réponse ne mène pas à une annulation.');

        $this->assertSame(0, BookingCancellationV2::query()->where('booking_id', $mission->booking_id)->count());
    }

    /** LE TÉMOIN : une réponse ordinaire, elle, annule bien. */
    public function test_le_prestataire_annule_avant_le_demarrage(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->prestataire)
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'provider'])
            ->call('ouvrir')
            ->set('optionChoisie', 'provider_unable')
            ->set('precision', 'Panne de véhicule sur la route.')
            ->call('confirmer')
            ->assertSet('erreur', null);

        $this->assertSame(1, BookingCancellationV2::query()->where('booking_id', $mission->booking_id)->count());
    }

    /** ANNULER ET ABANDONNER SONT DEUX FAITS DIFFÉRENTS. */
    public function test_le_prestataire_ne_peut_plus_annuler_une_mission_demarree(): void
    {
        $mission = $this->mission(demarree: Carbon::now()->subMinutes(10));

        Livewire::actingAs($this->prestataire)
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'provider'])
            ->assertForbidden();
    }

    /** LE TÉMOIN : le client, lui, peut encore annuler une mission démarrée. */
    public function test_le_client_peut_encore_annuler_une_mission_demarree(): void
    {
        $mission = $this->mission(demarree: Carbon::now()->subMinutes(10));

        Livewire::actingAs($this->client)
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'client'])
            ->assertOk();
    }

    public function test_un_tiers_n_annule_pas(): void
    {
        $mission = $this->mission();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(AnnulerLaMission::class, ['booking' => $mission->booking, 'role' => 'client'])
            ->assertForbidden();
    }
}
