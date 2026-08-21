<?php

namespace Tests\Feature\Kyc;

use App\Models\KycVerification;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
    }

    public function test_start_endpoint_creates_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'good@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/provider/kyc/start', [
            'country_code' => 'BE',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['verification_id', 'provider', 'status', 'decision']);
        $this->assertSame('mock', $response->json('provider'));
    }

    public function test_status_endpoint_returns_latest_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
            'decision' => KycVerification::DECISION_PENDING,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/provider/kyc/status');
        $response->assertOk();
        $response->assertJson([
            'has_verification' => true,
            'provider' => 'mock',
            'status' => 'in_review',
        ]);
    }

    /**
     * L'ÉCRAN DEMANDE `verified` ; LA RÉPONSE NE L'ENVOYAIT PAS.
     *
     * `KYCScreen` teste `status.verified` pour choisir entre « Vérifié » et « Complétez la
     * vérification ». Ce champ n'existait dans aucune des deux réponses : la branche « pas encore
     * vérifié » était donc la seule atteignable. Relevé à l'écran dans l'application prestataire —
     * badge « Vérifiée » au-dessus de « Complétez la vérification pour recevoir des missions ».
     */
    public function test_status_dit_explicitement_si_l_identite_est_verifiee(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_CLEAR,
            'decision' => KycVerification::DECISION_APPROVED,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/provider/kyc/status')
            ->assertOk()
            ->assertJsonPath('verified', true);
    }

    /**
     * TÉMOIN POSITIF : un profil non validé doit rendre `false`, et non « toujours vrai ».
     *
     * Sans lui, on remplacerait un champ toujours absent par un champ toujours vrai — un
     * prestataire non vérifié se croirait autorisé à recevoir des missions.
     */
    public function test_status_rend_faux_tant_que_le_profil_n_est_pas_valide(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
            'decision' => KycVerification::DECISION_PENDING,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/provider/kyc/status')
            ->assertOk()
            ->assertJsonPath('verified', false);
    }

    public function test_status_returns_false_when_no_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/provider/kyc/status');
        $response->assertOk();
        $response->assertJson(['has_verification' => false]);
    }

    public function test_sync_forbidden_for_other_user_verification(): void
    {
        $userA = User::factory()->create(['role' => 'employe']);
        $userB = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $userB->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = KycVerification::create([
            'user_id' => $userB->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
            'decision' => KycVerification::DECISION_PENDING,
            'external_applicant_id' => 'mock_app_aaa',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($userA);

        $this->postJson('/api/provider/kyc/verifications/'.$verification->id.'/sync')
            ->assertStatus(403);
    }
}
