<?php

namespace Tests\Feature\Missions;

use App\Livewire\Employe\MissionExecutionBoard;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklist;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionChecklistService;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * LA CHECKLIST QUI EMPÊCHAIT DE TERMINER — deux défauts trouvés en déroulant le parcours à la main.
 *
 * Aucun des deux n'était visible depuis la suite : les tests de cycle de vie construisent leurs
 * missions à la main, sans passer par la création depuis une réservation, et n'utilisent pas
 * l'écran terrain. Il a fallu commander une course dans un navigateur, la conduire, et se heurter
 * au mur.
 *
 *  1. Une COURSE recevait la checklist par défaut — celle du ménage. « Nettoyer surfaces clés »
 *     était obligatoire, donc la course ne se terminait jamais : ni encaissement, ni avis client,
 *     et un chauffeur qui reste « occupé » indéfiniment.
 *
 *  2. Trois vocabulaires se partageaient une seule colonne. La migration déclare « todo, done », la
 *     porte de clôture lit `done`, ce service écrivait `pending` et l'écran terrain basculait vers
 *     `completed`. Cocher les six tâches affichait 100 % et la mission refusait toujours de se
 *     terminer — le refus paraissait absurde, et il l'était.
 */
class ChecklistDeMissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Mission}
     */
    private function mission(bool $course): array
    {
        $client = User::factory()->client()->create();
        $prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ] + ($course ? [
            'dropoff_address' => 'Aéroport de Bruxelles',
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
        ] : []));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $prestataire->id,
            'status' => MissionStatus::STARTED,
            'destination_lat' => 50.8467,
            'destination_lng' => 4.3525,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $prestataire->id,
        ]);

        return [$prestataire, $mission];
    }

    public function test_une_course_ne_recoit_aucune_checklist_de_menage(): void
    {
        [, $mission] = $this->mission(course: true);

        $this->assertNull(
            app(MissionChecklistService::class)->ensureChecklist($mission),
            'Un chauffeur arrivé à destination devait cocher « Nettoyer surfaces clés » pour terminer sa course.'
        );

        $this->assertSame(0, MissionChecklist::where('mission_id', $mission->id)->count());
    }

    /** LE TÉMOIN : une intervention ordinaire garde bien sa checklist et ses tâches. */
    public function test_une_intervention_ordinaire_garde_sa_checklist(): void
    {
        [, $mission] = $this->mission(course: false);

        $checklist = app(MissionChecklistService::class)->ensureChecklist($mission);

        $this->assertNotNull($checklist);
        $this->assertGreaterThan(0, $checklist->items()->count());
    }

    /**
     * LE VOCABULAIRE : ce que l'écran terrain écrit doit ouvrir la porte de clôture.
     *
     * Le test coche par l'écran et termine par le service — les deux extrémités du désaccord.
     */
    public function test_cocher_les_taches_depuis_l_ecran_terrain_permet_de_terminer(): void
    {
        [$prestataire, $mission] = $this->mission(course: false);
        $checklist = app(MissionChecklistService::class)->ensureChecklist($mission);

        $this->actingAs($prestataire);
        $composant = Livewire::test(MissionExecutionBoard::class, ['mission' => $mission]);

        foreach ($checklist->items as $item) {
            $composant->call('toggleChecklistItem', $item->id);
        }

        $this->assertSame(
            0,
            $checklist->items()->where('is_required', true)->where('status', '!=', MissionChecklistService::FAITE)->count(),
            'L’écran écrivait « completed », que ni la porte de clôture ni l’avancement ne reconnaissent.'
        );

        $this->assertSame(100, $checklist->fresh()->completion_rate);

        // Et la mission se termine réellement — c'est ce que le prestataire tentait de faire.
        app(MissionLifecycleService::class)->completeMission($mission->fresh(), $prestataire);

        $this->assertSame(MissionStatus::COMPLETED, $mission->fresh()->status);
    }

    /**
     * LE TÉMOIN INVERSE, sans lequel le test précédent passerait au vert si la porte ne gardait
     * plus rien : une tâche obligatoire non cochée bloque toujours la clôture.
     */
    public function test_une_tache_obligatoire_non_cochee_bloque_toujours(): void
    {
        [$prestataire, $mission] = $this->mission(course: false);
        app(MissionChecklistService::class)->ensureChecklist($mission);

        $this->expectException(RuntimeException::class);

        app(MissionLifecycleService::class)->completeMission($mission->fresh(), $prestataire);
    }
}
