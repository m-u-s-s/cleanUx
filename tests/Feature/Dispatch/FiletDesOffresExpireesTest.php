<?php

namespace Tests\Feature\Dispatch;

use App\Models\MissionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** UNE OFFRE OUBLIÉE EST UNE MISSION PERDUE. */
class FiletDesOffresExpireesTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_offre_oubliee_est_enfin_expiree(): void
    {
        Queue::fake();

        $offre = $this->offreOubliee();

        $this->artisan('dispatch:balayer-les-offres-expirees')
            ->expectsOutputToContain('Offres expirées oubliées : 1')
            ->assertSuccessful();

        $this->assertSame('expired', $offre->refresh()->assignment_status);
        $this->assertNotNull($offre->declined_at);
    }

    /** LE BATTEMENT PROTÈGE LE JOB DIFFÉRÉ SUR SON PROPRE CRÉNEAU. */
    public function test_une_offre_a_peine_expiree_est_laissee_au_job(): void
    {
        Queue::fake();

        $offre = $this->offreOubliee(expireeDepuisSecondes: 5);

        $this->artisan('dispatch:balayer-les-offres-expirees --grace=60')
            ->expectsOutputToContain('Offres expirées oubliées : 0')
            ->assertSuccessful();

        $this->assertSame('assigned', $offre->refresh()->assignment_status);
    }

    /** ON NE TOUCHE PAS À CE QUI A ÉTÉ RÉPONDU. */
    public function test_une_offre_acceptee_nest_jamais_reprise(): void
    {
        Queue::fake();

        $offre = $this->offreOubliee();
        $offre->forceFill(['assignment_status' => 'accepted'])->save();

        $this->artisan('dispatch:balayer-les-offres-expirees')->assertSuccessful();

        $this->assertSame('accepted', $offre->refresh()->assignment_status);
    }

    /** TÉMOIN : sans offre oubliée, le balayage ne fabrique rien. */
    public function test_temoin_sans_offre_oubliee_rien_ne_se_passe(): void
    {
        Queue::fake();

        $this->artisan('dispatch:balayer-les-offres-expirees')
            ->expectsOutputToContain('Offres expirées oubliées : 0')
            ->assertSuccessful();
    }

    /** Une offre sans échéance n'est pas oubliée : elle n'expire simplement pas. */
    public function test_une_offre_sans_echeance_est_ignoree(): void
    {
        Queue::fake();

        $offre = $this->offreOubliee();
        $offre->forceFill(['expires_at' => null])->save();

        $this->artisan('dispatch:balayer-les-offres-expirees')
            ->expectsOutputToContain('Offres expirées oubliées : 0')
            ->assertSuccessful();

        $this->assertSame('assigned', $offre->refresh()->assignment_status);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function offreOubliee(int $expireeDepuisSecondes = 3600): MissionAssignment
    {
        $scenario = SpineScenario::make()->build();

        return MissionAssignment::create([
            'mission_id' => $scenario->mission->id,
            'user_id' => $scenario->provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now()->subSeconds($expireeDepuisSecondes + 20),
            'expires_at' => now()->subSeconds($expireeDepuisSecondes),
        ]);
    }
}
