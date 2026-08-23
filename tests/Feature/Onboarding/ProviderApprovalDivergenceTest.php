<?php

namespace Tests\Feature\Onboarding;

use App\Livewire\Admin\Providers\ProviderRegistrationsCenter;
use App\Models\KycVerification;
use App\Models\OnboardingProgress;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingEngine;
use Database\Seeders\ProviderOnboardingJourneySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Les deux voies d'approbation doivent dire la même chose du même prestataire. */
class ProviderApprovalDivergenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incomplete_dossier_cannot_be_approved_silently(): void
    {
        $profile = $this->selfRegistered();

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('approve', $profile->id)
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertSame(
            'pending',
            $profile->status,
            "un dossier incomplet ne s'approuve pas sans motif"
        );
    }

    public function test_an_incomplete_dossier_can_be_forced_with_a_recorded_reason(): void
    {
        $profile = $this->selfRegistered();

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->set("overrideReason.{$profile->id}", 'Pièces reçues par courrier, contrôlées en agence')
            ->call('approve', $profile->id);

        $profile->refresh();
        $this->assertSame('active', $profile->status);
        $this->assertSame(
            'Pièces reçues par courrier, contrôlées en agence',
            $profile->metadata['registration_override_reason'] ?? null,
        );
        $this->assertNotEmpty(
            $profile->metadata['registration_approved_with_blockers'] ?? [],
            'ce qui manquait au moment du passage en force doit rester consigné'
        );
    }

    /** Le cœur de la divergence : approuver l'accès n'est pas certifier une identité. */
    public function test_forcing_an_incomplete_dossier_never_claims_a_verification(): void
    {
        $profile = $this->selfRegistered();

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->set("overrideReason.{$profile->id}", 'Dérogation direction')
            ->call('approve', $profile->id);

        $this->assertSame('unverified', $profile->refresh()->verification_status);
    }

    public function test_a_complete_dossier_is_approved_and_marked_verified(): void
    {
        $profile = $this->completeDossier();

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('approve', $profile->id);

        $profile->refresh();
        $this->assertSame('active', $profile->status);
        $this->assertSame('verified', $profile->verification_status);
        $this->assertNotNull($profile->verified_at);
    }

    /** L'autre moitié de la divergence : le parcours v2 n'était jamais synchronisé, si bien qu'un prestataire approuvé gardait un cockpit affichant des étapes en attente. */
    public function test_approving_a_complete_dossier_closes_the_v2_journey(): void
    {
        $profile = $this->completeDossier();

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('approve', $profile->id);

        $progress = OnboardingProgress::where('user_id', $profile->user_id)->firstOrFail();
        $this->assertSame(OnboardingProgress::STATUS_COMPLETED, $progress->status);
        $this->assertEqualsWithDelta(100, (float) $progress->percent_complete, 0.01);
    }

    /** Marquer 100 % un parcours dont les étapes ne sont pas franchies ferait mentir le cockpit du prestataire, qui afficherait un dossier terminé avec des cartes encore à faire. */
    public function test_forcing_an_incomplete_dossier_does_not_close_the_v2_journey(): void
    {
        $profile = $this->selfRegistered();
        app(OnboardingEngine::class)->startFor($profile->user);

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->set("overrideReason.{$profile->id}", 'Dérogation')
            ->call('approve', $profile->id);

        $progress = OnboardingProgress::where('user_id', $profile->user_id)->first();
        $this->assertNotSame(OnboardingProgress::STATUS_COMPLETED, $progress?->status);
    }

    /** Un prestataire d'avant ce lot n'apparaît pas ici et n'est donc jamais touché. */
    public function test_a_legacy_provider_is_out_of_scope(): void
    {
        $user = User::factory()->employe()->create();
        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->assertDontSee($user->name);

        $this->assertSame('pending', $profile->refresh()->status);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function selfRegistered(): ProviderProfile
    {
        $this->seed(ProviderOnboardingJourneySeeder::class);

        $user = User::factory()->employe()->create(['name' => 'Jean Prestataire']);

        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        // `self_registered_at` n'est pas assignable en masse — c'est lui qui porte la
        // restriction d'accès, le rendre fillable permettrait de la lever depuis n'importe quelle
        // requête. Le poser via create() le perdrait silencieusement, et le profil sortirait du
        // périmètre de cet écran.
        $profile->forceFill(['self_registered_at' => now()])->save();

        return $profile->refresh();
    }

    /** Dossier réellement complet : toutes les étapes franchies, la pièce d'identité approuvée, et l'identité vérifiée. */
    private function completeDossier(): ProviderProfile
    {
        $profile = $this->selfRegistered();
        $user = $profile->user;

        $trade = Trade::query()->create([
            'code' => 'GRD',
            'name' => 'Jardinage',
            'slug' => 'jardinage-test',
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

        ProviderOnboardingDocument::create([
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            'status' => ProviderOnboardingDocument::STATUS_APPROVED,
            'file_path' => 'providers/cni.pdf',
            'file_name' => 'cni.pdf',
        ]);

        // L'identité est vérifiée parce qu'une décision KYC le dit, et non parce que le champ a
        // été pré-rempli : ce champ est précisément ce que l'approbation doit écrire, et le
        // poser d'avance ferait passer le test sans que la méthode y soit pour quoi que ce soit.
        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            // Vocabulaire réel : `clear` est un STATUT, la décision favorable est `approved`.
            // Les inventer faisait passer ce test pour de mauvaises raisons.
            'status' => 'clear',
            'decision' => 'approved',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $progress->completions()->update(['status' => 'completed', 'completed_at' => now()]);

        return $profile->fresh();
    }
}
