<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionChecklistItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionChecklistService;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionTodoService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * LE GABARIT PROPOSE, IL N'IMPOSE PLUS.
 *
 * Il posait six tâches OBLIGATOIRES génériques — « Nettoyer surfaces clés », « Rangement du
 * matériel » — sur toute mission non-course. Deux conséquences, et la seconde est la vraie :
 *
 *  1. le prestataire cochait six cases que personne ne lui avait demandées ;
 *  2. et surtout, ce que le CLIENT voulait n'était nulle part. Il n'avait aucun moyen de dire
 *     « la hotte, surtout », et la seule liste qui bloquait la clôture ignorait sa demande.
 *
 * Le savoir-faire n'est pas jeté : il devient `suggestionsPour()`, que le client ajoute d'un tap.
 * Ce qui change, c'est qui décide.
 */
class ChecklistGabaritTest extends TestCase
{
    use RefreshDatabase;

    private function mission(bool $course = false): Mission
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
        ] + ($course ? ['dropoff_lat' => 50.9010, 'dropoff_lng' => 4.4844] : []));

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

        return $mission->fresh('booking');
    }

    public function test_une_mission_neuve_n_a_plus_de_tache_imposee(): void
    {
        $mission = $this->mission();

        $checklist = app(MissionChecklistService::class)->ensureChecklist($mission);

        $this->assertNotNull($checklist, 'la liste existe : c\'est elle qui accueillera le client');
        $this->assertSame(
            0,
            MissionChecklistItem::query()->where('mission_checklist_id', $checklist->id)->count(),
            'et elle est vide : c\'est au client de la remplir',
        );
    }

    public function test_le_gabarit_survit_en_suggestions(): void
    {
        $suggestions = app(MissionChecklistService::class)->suggestionsPour($this->mission());

        $this->assertNotEmpty($suggestions, 'le savoir-faire qualité reste proposé');
        $this->assertContainsOnly('string', $suggestions);
    }

    /** LE TÉMOIN de la non-régression : une course n'a toujours aucune checklist du tout. */
    public function test_une_course_n_a_toujours_aucune_checklist(): void
    {
        $this->assertNull(app(MissionChecklistService::class)->ensureChecklist($this->mission(course: true)));
    }

    public function test_une_course_n_a_pas_de_suggestions(): void
    {
        $this->assertSame([], app(MissionChecklistService::class)->suggestionsPour($this->mission(course: true)));
    }

    /**
     * LE COMPORTEMENT DEMANDÉ, prouvé de bout en bout : « si la to-do list est vide il peut fermer
     * la mission sans valider de tâches, parce que le client ne les aura pas mises ».
     *
     * Ce test ne touche PAS la porte de clôture — `assertRequiredChecklistCompleted()` n'a pas
     * bougé d'une ligne. Elle refusait déjà sur des tâches obligatoires ouvertes, et il n'y en a
     * simplement plus tant que personne n'en demande.
     */
    public function test_une_liste_vide_laisse_la_cloture_passer(): void
    {
        $mission = $this->mission();
        $prestataire = $mission->leadProvider;

        app(MissionChecklistService::class)->ensureChecklist($mission);

        $ferme = app(MissionLifecycleService::class)->completeMission($mission->fresh(), $prestataire);

        $this->assertSame(MissionStatus::COMPLETED, $ferme->status);
    }

    /**
     * LE TÉMOIN INVERSE — sans lui, le test ci-dessus passerait au vert si la porte ne gardait plus
     * rien du tout, et l'on croirait avoir livré une règle alors qu'on aurait supprimé une garde.
     */
    public function test_une_tache_du_client_bloque_toujours_la_cloture(): void
    {
        $mission = $this->mission();
        $prestataire = $mission->leadProvider;

        app(MissionTodoService::class)->ajouter($mission, $mission->booking->client, 'Nettoyer la hotte');

        $this->expectException(RuntimeException::class);

        app(MissionLifecycleService::class)->completeMission($mission->fresh(), $prestataire);
    }
}
