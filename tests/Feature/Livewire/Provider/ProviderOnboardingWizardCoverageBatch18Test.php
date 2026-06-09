<?php

namespace Tests\Feature\Livewire\Provider;

use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class ProviderOnboardingWizardCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    private function provider(array $attributes = []): User
    {
        return User::factory()->employe()->create($attributes);
    }

    public function test_mount_starts_onboarding_and_prefills_user_fields(): void
    {
        $user = $this->provider(['name' => 'Pierre Dupont', 'phone' => '+32470111222']);
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->assertOk()
            ->assertSet('currentStep', 0)
            ->assertSet('name', 'Pierre Dupont')
            ->assertSet('phone', '+32470111222');

        $this->assertDatabaseHas('provider_profiles', ['user_id' => $user->id]);
    }

    public function test_mount_loads_existing_profile_state(): void
    {
        $user = $this->provider();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'onboarding_step' => 4,
            'bio' => 'Expert nettoyage',
            'skills' => ['plumbing', 'painting'],
            'metadata' => [
                'tax_id' => 'BE0123456789',
                'service_zone_ids' => [3, 7],
            ],
        ]);
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->assertSet('currentStep', 4)
            ->assertSet('bio', 'Expert nettoyage')
            ->assertSet('taxId', 'BE0123456789')
            ->assertSet('selectedSkills', ['plumbing', 'painting'])
            ->assertSet('selectedZones', [3, 7]);
    }

    public function test_go_to_step_rejects_out_of_range_values(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('goToStep', -1)
            ->assertSet('currentStep', 0)
            ->call('goToStep', 7)
            ->assertSet('currentStep', 0);
    }

    public function test_go_to_step_blocks_jumping_ahead(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('goToStep', 4)
            ->assertSet('currentStep', 0);
    }

    public function test_go_to_step_allows_navigating_backwards(): void
    {
        $user = $this->provider();
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'onboarding_step' => 4,
        ]);
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('message', 'lingering')
            ->call('goToStep', 2)
            ->assertSet('currentStep', 2)
            ->assertSet('message', null);
    }

    public function test_save_step0_persists_profile_basics(): void
    {
        Storage::fake('public');
        $user = $this->provider();
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('name', 'Nadia K')
            ->set('phone', '+32498765432')
            ->set('bio', 'Disponible 7j/7')
            ->set('photo', null)
            ->call('saveStep0')
            ->assertSet('currentStep', 1)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nadia K']);
        $this->assertDatabaseHas('provider_profiles', [
            'user_id' => $user->id,
            'bio' => 'Disponible 7j/7',
        ]);
    }

    public function test_save_step0_validates_required_name(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('name', '')
            ->call('saveStep0')
            ->assertHasErrors(['name' => 'required'])
            ->assertSet('currentStep', 0);
    }

    public function test_save_step1_uploads_identity_document(): void
    {
        Storage::fake('private');
        $user = $this->provider();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('id.pdf', 120, 'application/pdf');

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('identityType', 'passport')
            ->set('identityFile', $file)
            ->call('saveStep1')
            ->assertSet('currentStep', 2)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('provider_onboarding_documents', [
            'user_id' => $user->id,
            'document_type' => 'passport',
        ]);
    }

    public function test_save_step1_validates_missing_file(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('identityFile', null)
            ->call('saveStep1')
            ->assertHasErrors(['identityFile' => 'required'])
            ->assertSet('currentStep', 0);
    }

    public function test_save_step2_persists_tax_id(): void
    {
        $user = $this->provider();
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('taxId', 'FR12345678901')
            ->call('saveStep2')
            ->assertSet('currentStep', 3)
            ->assertSet('messageType', 'success');

        $this->assertSame(
            'FR12345678901',
            ProviderProfile::where('user_id', $user->id)->first()->metadata['tax_id'],
        );
    }

    public function test_save_step2_validates_short_tax_id(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('taxId', 'AB')
            ->call('saveStep2')
            ->assertHasErrors(['taxId' => 'min'])
            ->assertSet('currentStep', 0);
    }

    public function test_save_step3_uploads_insurance_document(): void
    {
        Storage::fake('private');
        $user = $this->provider();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('insurance.pdf', 90, 'application/pdf');

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('insuranceFile', $file)
            ->call('saveStep3')
            ->assertSet('currentStep', 4)
            ->assertSet('messageType', 'success');

        $this->assertDatabaseHas('provider_onboarding_documents', [
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_INSURANCE,
        ]);
    }

    public function test_save_step4_persists_skills_and_zones(): void
    {
        $user = $this->provider();
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('selectedSkills', ['plumbing', 'gardening'])
            ->set('selectedZones', [11, 12])
            ->call('saveStep4')
            ->assertSet('currentStep', 5)
            ->assertSet('messageType', 'success');

        $profile = ProviderProfile::where('user_id', $user->id)->first();
        $this->assertSame(['plumbing', 'gardening'], $profile->skills);
        $this->assertSame([11, 12], $profile->metadata['service_zone_ids']);
    }

    public function test_save_step4_validates_required_skills(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('selectedSkills', [])
            ->call('saveStep4')
            ->assertHasErrors(['selectedSkills' => 'required'])
            ->assertSet('currentStep', 0);
    }

    public function test_refresh_stripe_status_reports_inactive(): void
    {
        $user = $this->provider(['stripe_connect_status' => 'pending']);
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('refreshStripeStatus')
            ->assertSet('messageType', 'info');
    }

    public function test_refresh_stripe_status_advances_when_active(): void
    {
        $user = $this->provider(['stripe_connect_status' => 'active']);
        $this->actingAs($user);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('refreshStripeStatus')
            ->assertSet('currentStep', 6)
            ->assertSet('messageType', 'success');
    }

    public function test_start_stripe_onboarding_dispatches_link(): void
    {
        $user = $this->provider();
        $this->actingAs($user);

        $mock = Mockery::mock(StripeConnectService::class);
        $mock->shouldReceive('onboardingLink')->andReturn('https://connect.stripe.test/link');
        $this->app->instance(StripeConnectService::class, $mock);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('startStripeOnboarding')
            ->assertDispatched('open-stripe-link', url: 'https://connect.stripe.test/link');
    }

    public function test_start_stripe_onboarding_handles_failure(): void
    {
        $user = $this->provider();
        $this->actingAs($user);

        $mock = Mockery::mock(StripeConnectService::class);
        $mock->shouldReceive('onboardingLink')->andThrow(new \RuntimeException('stripe down'));
        $this->app->instance(StripeConnectService::class, $mock);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('startStripeOnboarding')
            ->assertSet('messageType', 'error');
    }

    public function test_clear_message_resets_state(): void
    {
        $this->actingAs($this->provider());

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('taxId', 'BE0123456789')
            ->call('saveStep2')
            ->assertSet('messageType', 'success')
            ->call('clearMessage')
            ->assertSet('message', null)
            ->assertSet('messageType', null);
    }

    public function test_computed_properties_expose_zones_and_documents(): void
    {
        $user = $this->provider();
        ServiceZone::factory()->create(['status' => 'active']);
        ServiceZone::factory()->create(['status' => 'inactive']);
        ProviderOnboardingDocument::factory()->create([
            'user_id' => $user->id,
            'document_type' => ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
        ]);
        $this->actingAs($user);

        $component = Livewire::test(ProviderOnboardingWizard::class)->assertOk();

        $this->assertSame(1, $component->instance()->zones->count());
        $this->assertTrue($component->instance()->documents->has(ProviderOnboardingDocument::TYPE_IDENTITY_CARD));
    }
}
