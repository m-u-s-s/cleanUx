<?php

namespace Tests\Feature\Missions;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionVerificationCode;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MissionEndCodeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_requires_end_code_when_pending_code_exists(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Le code de fin est requis pour clôturer cette mission.');
    }

    public function test_complete_rejects_wrong_end_code(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('999999'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete", [
                'end_code' => '111111',
            ]);

        // 422, pas 500 : un code erroné est une saisie invalide, pas une panne du serveur.
        // Cette assertion exigeait auparavant assertServerError(), gravant comme attendu un
        // défaut bien réel — MissionVerificationCodeService levait des RuntimeException sans code
        // HTTP, que le rendu JSON ne pouvait donc que traiter en 500. Côté mobile, l'écran en
        // déduisait « Le service est momentanément indisponible » là où il fallait dire au
        // prestataire que le code était faux.
        $response->assertStatus(422);
        $this->assertSame('started', $mission->fresh()->status);
    }

    public function test_complete_succeeds_with_valid_end_code(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        MissionVerificationCode::factory()->endCode()->create([
            'mission_id' => $mission->id,
            'code_hash' => Hash::make('123456'),
            'is_consumed' => false,
        ]);

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete", [
                'end_code' => '123456',
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'completed');
    }

    /** CE TEST FIGEAIT LE TROU — comme son jumeau dans le lot 13. */
    public function test_complete_refuses_without_end_code_when_the_mission_requires_one(): void
    {
        [$provider, $mission] = $this->makeStartedMission();

        // `fresh()` et pas le modèle en mémoire : `requires_end_code` vient du DÉFAUT de la
        // colonne (`true`), que l'objet créé ne porte pas tant qu'on ne l'a pas relu.
        $this->assertTrue(
            (bool) $mission->fresh()->requires_end_code,
            'Témoin : la mission exige bien un code.'
        );

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete")
            ->assertStatus(422);

        $this->assertSame('started', $mission->fresh()->status);

        // LE TÉMOIN : sans exigence, la même clôture passe.
        $mission->update(['requires_end_code' => false]);

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/missions/{$mission->id}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    protected function makeStartedMission(): array
    {
        $client = User::factory()->create();
        $provider = User::factory()->employe()->create();
        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
        ]);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $provider->id,
            'status' => 'started',
            'actual_start_at' => now()->subHour(),
            'planned_start_at' => now()->subHour(),
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
        ]);

        return [$provider, $mission];
    }
}
