<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\MissionLiveTracking;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\User;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE SUIVI WEB NE S'ARRÊTE PLUS À LA PORTE.
 *
 * La page client montrait un trajet, puis un point immobile pendant deux heures. Ce qu'on vérifie
 * ici, c'est que le web lit EXACTEMENT ce que lit l'application mobile — mêmes services, mêmes
 * filtres. Deux assemblages distincts pour une même intervention finiraient par se contredire, et
 * c'est le jour du litige qu'on s'en apercevrait.
 */
class SuiviSurPlaceWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        Notification::fake();
    }

    public function test_le_client_voit_ses_photos_et_le_deroule(): void
    {
        [$client, $provider, $mission] = $this->scenario();

        app(MissionMediaService::class)->capture(
            $mission,
            $provider,
            UploadedFile::fake()->create('avant.jpg', 100, 'image/jpeg'),
            MissionMedia::TYPE_BEFORE_PHOTO,
        );

        Livewire::actingAs($client)
            ->test(MissionLiveTracking::class, ['mission' => $mission])
            ->assertSee('Déroulé de l’intervention', false)
            ->assertSee('Avant');
    }

    public function test_un_imprevu_signale_apparait_chez_le_client(): void
    {
        [$client, $provider, $mission] = $this->scenario();

        app(MissionIncidentService::class)->report(
            $mission,
            $provider,
            MissionIncident::TYPE_ACCESS_IMPOSSIBLE,
            'Portail fermé, personne ne répond.',
        );

        Livewire::actingAs($client)
            ->test(MissionLiveTracking::class, ['mission' => $mission])
            ->assertSee('Accès impossible')
            ->assertSee('Portail fermé, personne ne répond.');
    }

    /** Ce que le prestataire garde pour son équipe ne traverse pas. */
    public function test_un_cliche_reserve_a_lequipe_nest_pas_montre(): void
    {
        [$client, $provider, $mission] = $this->scenario();

        app(MissionMediaService::class)->capture(
            $mission,
            $provider,
            UploadedFile::fake()->create('interne.jpg', 100, 'image/jpeg'),
            MissionMedia::TYPE_AFTER_PHOTO,
            clientVisible: false,
        );

        $composant = Livewire::actingAs($client)
            ->test(MissionLiveTracking::class, ['mission' => $mission]);

        $this->assertSame([], $composant->instance()->photos()['after']);
    }

    public function test_un_autre_client_ne_peut_pas_ouvrir_la_page(): void
    {
        [, , $mission] = $this->scenario();

        Livewire::actingAs(User::factory()->create())
            ->test(MissionLiveTracking::class, ['mission' => $mission])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Mission}
     */
    private function scenario(): array
    {
        $client = User::factory()->create();
        $provider = User::factory()->employe()->create(['is_active' => true, 'status' => 'active']);

        $booking = Booking::factory()->create(['client_id' => $client->id]);

        $mission = Mission::query()->where('booking_id', $booking->id)->first()
            ?? Mission::factory()->create(['booking_id' => $booking->id]);

        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'lead_employee_id' => $provider->id,
            'lead_provider_user_id' => $provider->id,
            'actual_start_at' => now()->subHour(),
            'estimated_duration_minutes' => 120,
        ])->save();

        MissionAssignment::query()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subHours(2),
            'accepted_at' => now()->subHours(2),
        ]);

        return [$client, $provider, $mission->fresh()];
    }
}
