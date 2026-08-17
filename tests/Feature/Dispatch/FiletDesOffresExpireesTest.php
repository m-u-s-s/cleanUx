<?php

namespace Tests\Feature\Dispatch;

use App\Models\MissionAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * UNE OFFRE OUBLIÉE EST UNE MISSION PERDUE.
 *
 * L'expiration d'une offre repose sur `EscalateMissionAssignmentJob`, mis en file AVEC UN DÉLAI
 * jusqu'à `expires_at` et déclaré `tries = 1`. Efficace, et fragile : un worker redémarré pendant
 * que le job attend, une file vidée, un échec unique sur un hoquet de base — et plus rien ne se
 * déclenche jamais.
 *
 * L'offre reste alors `assigned` indéfiniment. Le prestataire ne répond pas, il ne l'a peut-être
 * même pas vue, la mission n'est JAMAIS proposée au suivant — l'escalade était précisément ce qui
 * devait la relancer — et le client attend quelqu'un qui ne viendra pas. Sans qu'une seule ligne
 * soit en erreur nulle part.
 *
 * CE BALAYAGE REJOUE LE MÊME CHEMIN, pas un chemin parallèle : `expireAndEscalate()` est appelé tel
 * quel. Écrire une seconde expiration ici en ferait une version qui divergerait — et ce serait
 * celle du balayage, jamais relue, qui déciderait du sort des missions oubliées.
 */
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

    /**
     * LE BATTEMENT PROTÈGE LE JOB DIFFÉRÉ SUR SON PROPRE CRÉNEAU.
     *
     * Une offre qui vient d'expirer appartient encore au job : le doubler ferait deux recherches de
     * candidat pour rien, à chaque passage. Le balayage ne prend que ce qui a manifestement été
     * perdu.
     */
    public function test_une_offre_a_peine_expiree_est_laissee_au_job(): void
    {
        Queue::fake();

        $offre = $this->offreOubliee(expireeDepuisSecondes: 5);

        $this->artisan('dispatch:balayer-les-offres-expirees --grace=60')
            ->expectsOutputToContain('Offres expirées oubliées : 0')
            ->assertSuccessful();

        $this->assertSame('assigned', $offre->refresh()->assignment_status);
    }

    /**
     * ON NE TOUCHE PAS À CE QUI A ÉTÉ RÉPONDU.
     *
     * Réexpirer une offre acceptée retirerait la mission à quelqu'un qui est peut-être déjà en
     * route. La garde vit dans `expireAndEscalate()` ; ce test vérifie qu'on la traverse bien.
     */
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
