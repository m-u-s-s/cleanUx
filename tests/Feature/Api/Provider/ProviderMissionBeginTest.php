<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Missions\MissionLifecycleService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Démarrage d'une mission depuis l'application prestataire.
 *
 * L'API exposait `start` (qui met en route), `arrive` et `complete` — mais RIEN pour faire passer
 * une mission de `arrived` à `started`. Ce passage n'existait que sur des routes web à session
 * (routes/missions.php), inaccessibles à une app authentifiée par jeton : un prestataire arrivé
 * sur place ne pouvait donc pas démarrer sa mission depuis son téléphone.
 *
 * L'écran, lui, proposait un bouton « Démarrer mission » qui postait vers /start — donc vers
 * setEnRoute — et recevait un 422, la transition arrived → en_route étant invalide.
 *
 * Le code de démarrage est envoyé au client par SMS à l'arrivée (setArrived) : c'est lui qui
 * atteste la présence. Cet endpoint le réclame, sur le modèle de `complete` et de son end_code.
 */
class ProviderMissionBeginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_provider_starts_an_arrived_mission_with_the_client_code(): void
    {
        [$provider, $mission] = $this->arrivedMission();
        $code = $this->pendingStartCode($mission);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/begin", ['start_code' => $code])
            ->assertOk()
            ->assertJsonPath('status', MissionStatus::STARTED);

        $this->assertSame(MissionStatus::STARTED, $mission->fresh()->status);
        $this->assertNotNull($mission->fresh()->actual_start_at);
    }

    /**
     * Le code atteste la présence du client : sans lui, la mission ne démarre pas.
     */
    public function test_starting_without_the_code_is_refused(): void
    {
        [$provider, $mission] = $this->arrivedMission();
        $this->pendingStartCode($mission);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/begin", [])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::ARRIVED, $mission->fresh()->status);
    }

    public function test_a_wrong_code_does_not_start_the_mission(): void
    {
        [$provider, $mission] = $this->arrivedMission();
        $this->pendingStartCode($mission);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/begin", ['start_code' => '000000'])
            ->assertStatus(422);

        $this->assertSame(MissionStatus::ARRIVED, $mission->fresh()->status);
    }

    /**
     * Un prestataire ne démarre que SES missions — même garde que les autres actions du cycle.
     */
    public function test_another_provider_cannot_start_the_mission(): void
    {
        [, $mission] = $this->arrivedMission();
        $code = $this->pendingStartCode($mission);
        $intruder = $this->makeProvider();

        $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/begin", ['start_code' => $code])
            ->assertForbidden();

        $this->assertSame(MissionStatus::ARRIVED, $mission->fresh()->status);
    }

    /** @return array{0: User, 1: Mission} */
    private function arrivedMission(): array
    {
        $provider = $this->makeProvider();

        $mission = Mission::create([
            'status' => MissionStatus::EN_ROUTE,
            'planned_start_at' => now(),
        ]);

        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'accepted',
        ]);

        // setArrived génère les codes de début et de fin et les envoie au client.
        app(MissionLifecycleService::class)->setArrived($mission, $provider, 50.85, 4.35);

        return [$provider, $mission->fresh()];
    }

    private function pendingStartCode(Mission $mission): string
    {
        // Le code en clair n'est pas relu depuis la base (il y est haché) : on en émet un neuf,
        // dont on connaît la valeur, ce que fait aussi le flux réel à chaque arrivée.
        return app(MissionLifecycleService::class)->generateStartCode($mission)['code'];
    }

    private function makeProvider(): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }
}
