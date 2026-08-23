<?php

namespace Tests\Feature\Onboarding;

use App\Models\KycVerification;
use App\Models\OnboardingProgress;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Onboarding\ProviderAutoApproval;
use App\Services\OnboardingV2\OnboardingEngine;
use Database\Seeders\ProviderOnboardingJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Activation automatique d'un compte prestataire. */
class ProviderAutoApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_complete_dossier_opens_the_account_without_an_admin(): void
    {
        $user = $this->providerWithCompleteDossier();

        $outcome = app(ProviderAutoApproval::class)->evaluate($user);

        $this->assertSame(ProviderAutoApproval::OUTCOME_APPROVED, $outcome);
        $this->assertSame('active', $user->providerProfile->fresh()->status);
    }

    /** Le point de la règle choisie : une pièce déposée mais pas encore relue n'empêche pas l'activation. */
    public function test_a_document_awaiting_review_does_not_delay_the_opening(): void
    {
        $user = $this->providerWithCompleteDossier(documentStatus: ProviderOnboardingDocument::STATUS_PENDING);

        $this->assertSame(
            ProviderAutoApproval::OUTCOME_APPROVED,
            app(ProviderAutoApproval::class)->evaluate($user),
        );
        $this->assertSame('active', $user->providerProfile->fresh()->status);
    }

    /** Mais être actif n'est pas être certifié : `verified` affirme une relecture humaine des pièces, et il ne doit pas être écrit tant qu'elle n'a pas eu lieu. */
    public function test_being_open_is_not_being_certified(): void
    {
        $user = $this->providerWithCompleteDossier(documentStatus: ProviderOnboardingDocument::STATUS_PENDING);

        app(ProviderAutoApproval::class)->evaluate($user);

        $profile = $user->providerProfile->fresh();
        $this->assertSame('active', $profile->status);
        $this->assertNotSame('verified', $profile->verification_status);
    }

    public function test_a_reviewed_document_lets_the_account_be_certified(): void
    {
        $user = $this->providerWithCompleteDossier(documentStatus: ProviderOnboardingDocument::STATUS_APPROVED);

        app(ProviderAutoApproval::class)->evaluate($user);

        $this->assertSame('verified', $user->providerProfile->fresh()->verification_status);
    }

    public function test_an_incomplete_dossier_stays_pending(): void
    {
        $user = $this->providerWithCompleteDossier(withDocument: false);

        $this->assertSame(
            ProviderAutoApproval::OUTCOME_INCOMPLETE,
            app(ProviderAutoApproval::class)->evaluate($user),
        );
        $this->assertSame('pending', $user->providerProfile->fresh()->status);
    }

    /** La garantie la plus importante : le robot n'a pas le droit de refuser. */
    public function test_a_refused_identity_never_closes_the_account(): void
    {
        $user = $this->providerWithCompleteDossier(kycDecision: 'rejected');

        $outcome = app(ProviderAutoApproval::class)->evaluate($user);

        $profile = $user->providerProfile->fresh();
        $this->assertSame(ProviderAutoApproval::OUTCOME_MANUAL_REVIEW, $outcome);
        $this->assertSame('pending', $profile->status, 'le robot ne refuse jamais : il oriente');
        $this->assertNotSame('rejected', $profile->status);
        $this->assertSame(
            ProviderAutoApproval::OUTCOME_MANUAL_REVIEW,
            $profile->metadata['auto_review_outcome'] ?? null,
        );
        $this->assertNotEmpty($profile->metadata['auto_review_reason'] ?? null);
    }

    /** Un prestataire d'avant l'inscription en libre-service n'est jamais concerné. */
    public function test_a_legacy_provider_is_left_alone(): void
    {
        $user = $this->providerWithCompleteDossier(selfRegistered: false);

        $this->assertSame(
            ProviderAutoApproval::OUTCOME_SKIPPED,
            app(ProviderAutoApproval::class)->evaluate($user),
        );
        $this->assertSame('pending', $user->providerProfile->fresh()->status);
    }

    /** Un compte déjà refusé par un humain ne doit pas être rouvert par le robot. */
    public function test_a_rejected_account_is_not_reopened(): void
    {
        $user = $this->providerWithCompleteDossier();
        $user->providerProfile->forceFill(['status' => 'rejected'])->save();

        $this->assertSame(
            ProviderAutoApproval::OUTCOME_SKIPPED,
            app(ProviderAutoApproval::class)->evaluate($user),
        );
        $this->assertSame('rejected', $user->providerProfile->fresh()->status);
    }

    /** Rejouer l'évaluation sur un compte déjà ouvert ne doit rien changer. */
    public function test_evaluating_twice_is_harmless(): void
    {
        $user = $this->providerWithCompleteDossier();
        $service = app(ProviderAutoApproval::class);

        $service->evaluate($user);
        $this->assertSame(ProviderAutoApproval::OUTCOME_SKIPPED, $service->evaluate($user));
        $this->assertSame('active', $user->providerProfile->fresh()->status);
    }

    /** L'ouverture clôt aussi le parcours, sans quoi le cockpit afficherait des étapes en attente. */
    public function test_opening_the_account_closes_the_journey(): void
    {
        $user = $this->providerWithCompleteDossier();

        app(ProviderAutoApproval::class)->evaluate($user);

        $progress = OnboardingProgress::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(OnboardingProgress::STATUS_COMPLETED, $progress->status);
    }

    /** Dossier complet : métier sans assurance ni certification exigées, pièce d'identité déposée, identité validée, toutes les étapes franchies. */
    private function providerWithCompleteDossier(
        string $documentStatus = ProviderOnboardingDocument::STATUS_APPROVED,
        string $kycDecision = 'approved',
        bool $withDocument = true,
        bool $selfRegistered = true,
    ): User {
        $this->seed(ProviderOnboardingJourneySeeder::class);

        $user = User::factory()->employe()->create();
        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        if ($selfRegistered) {
            // Non assignable en masse : c'est lui qui porte la restriction d'accès.
            $profile->forceFill(['self_registered_at' => now()])->save();
        }

        $trade = Trade::query()->create([
            'code' => 'GRD',
            'name' => 'Jardinage',
            'slug' => 'jardinage-auto',
            'is_active' => true,
            'requires_insurance_proof' => false,
            'requires_certification' => false,
        ]);
        DB::table('trade_user')->insert([
            'user_id' => $user->id,
            'trade_id' => $trade->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withDocument) {
            ProviderOnboardingDocument::create([
                'user_id' => $user->id,
                'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
                'status' => $documentStatus,
                'file_path' => 'providers/cni.pdf',
                'file_name' => 'cni.pdf',
            ]);
        }

        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => $kycDecision === 'approved' ? 'clear' : 'rejected',
            'decision' => $kycDecision,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $progress->completions()->update(['status' => 'completed', 'completed_at' => now()]);

        return $user->fresh();
    }
}
