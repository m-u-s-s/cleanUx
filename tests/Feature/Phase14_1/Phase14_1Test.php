<?php

namespace Tests\Feature\Phase14_1;

use App\Livewire\Admin\Onboarding\AdminOnboardingDocumentsCenter;
use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Phase14_1Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // ADMIN — Documents Center
    // ──────────────────────────────────────────────

    public function test_admin_can_render_documents_center(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['platform_role' => 'admin'])->save();

        Livewire::actingAs($admin)
            ->test(AdminOnboardingDocumentsCenter::class)
            ->assertOk();
    }

    public function test_admin_documents_center_filters_by_status(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create();
        $provider = User::factory()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'provider_type' => 'individual', 'status' => 'pending']);

        $svc = app(ProviderOnboardingService::class);
        $doc1 = $svc->uploadDocument($provider, 'identity_card',
            UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'));

        // Approuver le 1er
        $svc->reviewDocument($doc1, $admin, true);

        // Garder le 2e en pending
        $svc->uploadDocument($provider, 'insurance',
            UploadedFile::fake()->create('ins.pdf', 50, 'application/pdf'));

        Livewire::actingAs($admin)
            ->test(AdminOnboardingDocumentsCenter::class)
            ->set('filterStatus', 'approved')
            ->assertSee('id.pdf')
            ->assertDontSee('ins.pdf');
    }

    public function test_admin_can_approve_document(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create();
        $provider = User::factory()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'provider_type' => 'individual', 'status' => 'pending']);

        $doc = app(ProviderOnboardingService::class)->uploadDocument(
            $provider, 'identity_card',
            UploadedFile::fake()->create('id.pdf', 50, 'application/pdf')
        );

        Livewire::actingAs($admin)
            ->test(AdminOnboardingDocumentsCenter::class)
            ->call('approve', $doc->id);

        $this->assertSame('approved', $doc->fresh()->status);
        $this->assertSame($admin->id, $doc->fresh()->reviewed_by);
    }

    public function test_admin_can_reject_document_with_reason(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create();
        $provider = User::factory()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'provider_type' => 'individual', 'status' => 'pending']);

        $doc = app(ProviderOnboardingService::class)->uploadDocument(
            $provider, 'identity_card',
            UploadedFile::fake()->create('id.pdf', 50, 'application/pdf')
        );

        Livewire::actingAs($admin)
            ->test(AdminOnboardingDocumentsCenter::class)
            ->call('openRejectModal', $doc->id)
            ->set('rejectionReason', 'Document flou, illisible')
            ->call('reject');

        $fresh = $doc->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame('Document flou, illisible', $fresh->rejection_reason);
    }

    public function test_admin_reject_requires_reason(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create();
        $provider = User::factory()->create();
        ProviderProfile::create(['user_id' => $provider->id, 'provider_type' => 'individual', 'status' => 'pending']);

        $doc = app(ProviderOnboardingService::class)->uploadDocument(
            $provider, 'identity_card',
            UploadedFile::fake()->create('id.pdf', 50, 'application/pdf')
        );

        Livewire::actingAs($admin)
            ->test(AdminOnboardingDocumentsCenter::class)
            ->call('openRejectModal', $doc->id)
            ->set('rejectionReason', '')  // vide
            ->call('reject')
            ->assertHasErrors('rejectionReason');

        $this->assertSame('pending_review', $doc->fresh()->status);
    }

    // ──────────────────────────────────────────────
    // ADMIN — Providers List
    // ──────────────────────────────────────────────

    public function test_admin_providers_list_renders(): void
    {
        $admin = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AdminOnboardingProvidersList::class)
            ->assertOk();
    }

    public function test_admin_providers_list_counts_by_status(): void
    {
        $admin = User::factory()->create();

        // 1 in_progress (étape 2)
        $u1 = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $u1->id, 'provider_type' => 'individual',
            'status' => 'pending', 'verification_status' => 'pending',
            'onboarding_step' => 2,
        ]);

        // 1 ready (étape 5+)
        $u2 = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $u2->id, 'provider_type' => 'individual',
            'status' => 'pending', 'verification_status' => 'pending',
            'onboarding_step' => 5,
        ]);

        // 1 verified
        $u3 = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $u3->id, 'provider_type' => 'individual',
            'status' => 'active', 'verification_status' => 'verified',
            'onboarding_step' => 6, 'onboarding_completed_at' => now(),
        ]);

        $cmp = Livewire::actingAs($admin)->test(AdminOnboardingProvidersList::class);
        $counts = $cmp->get('counts');

        $this->assertSame(1, $counts['in_progress']);
        $this->assertSame(1, $counts['ready']);
        $this->assertSame(1, $counts['verified']);
    }

    public function test_admin_can_approve_onboarding_when_all_ready(): void
    {
        Storage::fake('private');
        $admin = User::factory()->create();

        $provider = User::factory()->create();
        $provider->update(['stripe_connect_status' => 'active']);

        $svc = app(ProviderOnboardingService::class);
        $svc->startOnboarding($provider);

        // Doc identité approved
        $idDoc = $svc->uploadDocument($provider, 'identity_card',
            UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'));
        $svc->reviewDocument($idDoc, $admin, true);

        // Doc insurance approved
        $insDoc = $svc->uploadDocument($provider, 'insurance',
            UploadedFile::fake()->create('ins.pdf', 50, 'application/pdf'));
        $svc->reviewDocument($insDoc, $admin, true);

        Livewire::actingAs($admin)
            ->test(AdminOnboardingProvidersList::class)
            ->call('approveOnboarding', $provider->id);

        $profile = ProviderProfile::where('user_id', $provider->id)->first();
        $this->assertSame('verified', $profile->verification_status);
        $this->assertSame('active', $profile->status);
        $this->assertNotNull($profile->onboarding_completed_at);
    }

    public function test_admin_approve_fails_without_documents(): void
    {
        $admin = User::factory()->create();
        $provider = User::factory()->create();
        $provider->update(['stripe_connect_status' => 'active']);

        app(ProviderOnboardingService::class)->startOnboarding($provider);

        Livewire::actingAs($admin)
            ->test(AdminOnboardingProvidersList::class)
            ->call('approveOnboarding', $provider->id);

        $profile = ProviderProfile::where('user_id', $provider->id)->first();
        $this->assertNotEquals('verified', $profile->verification_status);
    }

    // ──────────────────────────────────────────────
    // PROVIDER — Wizard
    // ──────────────────────────────────────────────

    public function test_provider_wizard_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->assertOk();

        // Auto-création du ProviderProfile au mount
        $this->assertDatabaseHas('provider_profiles', ['user_id' => $user->id]);
    }

    public function test_wizard_step0_validates_required_name(): void
    {
        $user = User::factory()->create(['name' => '']);

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->set('name', '')
            ->call('saveStep0')
            ->assertHasErrors('name');
    }

    public function test_wizard_step0_saves_and_advances(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->set('name', 'Jean Dupont')
            ->set('phone', '+32475123456')
            ->set('bio', 'Plombier 10 ans')
            ->call('saveStep0')
            ->assertSet('currentStep', 1);

        $this->assertSame('Jean Dupont', $user->fresh()->name);
    }

    public function test_wizard_step1_uploads_identity_doc(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->set('currentStep', 1)
            ->set('identityType', 'identity_card')
            ->set('identityFile', UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'))
            ->call('saveStep1')
            ->assertSet('currentStep', 2);

        $this->assertDatabaseHas('provider_onboarding_documents', [
            'user_id'       => $user->id,
            'document_type' => 'identity_card',
            'status'        => 'pending_review',
        ]);
    }

    public function test_wizard_step4_validates_at_least_one_skill(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->set('currentStep', 4)
            ->set('selectedSkills', [])
            ->call('saveStep4')
            ->assertHasErrors('selectedSkills');
    }

    public function test_wizard_step4_saves_skills(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->set('currentStep', 4)
            ->set('selectedSkills', ['plumbing', 'electrical'])
            ->call('saveStep4')
            ->assertSet('currentStep', 5);

        $profile = ProviderProfile::where('user_id', $user->id)->first();
        $this->assertEquals(['plumbing', 'electrical'], $profile->skills);
    }

    public function test_wizard_cannot_jump_ahead_unsaved_steps(): void
    {
        $user = User::factory()->create();

        // mount → currentStep=0, progress.current_step=0
        // try to jump to step 4 → should not advance
        Livewire::actingAs($user)
            ->test(ProviderOnboardingWizard::class)
            ->call('goToStep', 4)
            ->assertSet('currentStep', 0);
    }
}
