<?php

namespace Tests\Feature;

use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Phase 14.1 — Tests Livewire admin pour la liste d'onboarding des prestataires. */
class AdminOnboardingProvidersListTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    /**
     * L'E-MAIL EST DÉDUIT DU NOM, ET CE N'EST PAS DE LA COQUETTERIE.
     *
     * La recherche de l'écran porte sur `name` OU `email` (`AdminOnboardingProvidersList`,
     * clause `whereHas('user')`). Avec l'e-mail aléatoire de Faker, celui de « Other Person »
     * contenait parfois « zoe » : sa ligne remontait pour la recherche « Zoe » et
     * `test_search_filters_providers_by_name` tombait — une fois sur quelques dizaines, donc
     * seulement en suite complète. Un fixture déterministe supprime le hasard.
     */
    protected function makeInProgressProvider(string $name): ProviderProfile
    {
        $user = User::factory()->employe()->create([
            'name' => $name,
            'email' => Str::slug($name).'@exemple.test',
        ]);

        return ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'onboarding_step' => 2,
            'onboarding_completed_at' => null,
        ]);
    }

    public function test_mount_renders_with_default_filter_and_counts(): void
    {
        $this->makeInProgressProvider('Alice Onboard');
        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->assertOk()
            ->assertSet('filterStatus', 'in_progress')
            ->assertSee('Onboarding prestataires')
            ->assertSee('Alice Onboard');
    }

    public function test_filter_ready_and_verified_branches(): void
    {
        // Ready: not verified, onboarding_step >= 5
        $ready = User::factory()->employe()->create(['name' => 'Ready Provider']);
        ProviderProfile::factory()->create([
            'user_id' => $ready->id,
            'status' => 'pending',
            'verification_status' => 'pending',
            'onboarding_step' => 6,
            'onboarding_completed_at' => null,
        ]);

        // Verified
        $verified = User::factory()->employe()->create(['name' => 'Verified Provider']);
        ProviderProfile::factory()->create([
            'user_id' => $verified->id,
            'status' => 'active',
            'verification_status' => 'verified',
            'onboarding_step' => 6,
            'onboarding_completed_at' => now(),
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->set('filterStatus', 'ready')
            ->assertSee('Ready Provider')
            ->set('filterStatus', 'verified')
            ->assertSee('Verified Provider')
            ->set('filterStatus', 'all')
            ->assertSee('Ready Provider')
            ->assertSee('Verified Provider');
    }

    public function test_search_filters_providers_by_name(): void
    {
        $this->makeInProgressProvider('Zoe Searchable');
        $this->makeInProgressProvider('Other Person');

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->set('filterStatus', 'all')
            // TEMOIN — les deux prestataires sont bien listes SANS recherche. Sans lui,
            // l'assertion d'absence ci-dessous passerait au vert sur une liste vide.
            ->assertSee('Zoe Searchable')
            ->assertSee('Other Person')
            ->set('search', 'Zoe')
            ->assertSee('Zoe Searchable')
            ->assertDontSee('Other Person');
    }

    /**
     * LA BRANCHE QUI A FAIT TOMBER LA SUITE, ET QUE RIEN NE COUVRAIT.
     *
     * La recherche porte sur `name` OU `email`. Le `orWhere` n'etait exerce par aucun test :
     * il n'apparaissait que par accident, quand un e-mail Faker contenait le terme cherche.
     */
    public function test_search_filters_providers_by_email(): void
    {
        $this->makeInProgressProvider('Zoe Searchable');
        $this->makeInProgressProvider('Other Person');

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->set('filterStatus', 'all')
            ->set('search', 'other-person@exemple.test')
            ->assertSee('Other Person')
            ->assertDontSee('Zoe Searchable');
    }

    public function test_clear_filters_resets_search_and_status(): void
    {
        $this->makeInProgressProvider('Reset Me');
        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->set('filterStatus', 'verified')
            ->set('search', 'something')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('filterStatus', 'in_progress');
    }

    public function test_refresh_counts_action_runs(): void
    {
        $this->makeInProgressProvider('Counted Provider');
        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->call('refreshCounts')
            ->assertOk();
    }

    public function test_documents_count_for_returns_status_breakdown(): void
    {
        $provider = $this->makeInProgressProvider('Doc Provider');
        $userId = $provider->user_id;

        ProviderOnboardingDocument::factory()->create([
            'user_id' => $userId,
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
        ]);
        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $userId]);
        ProviderOnboardingDocument::factory()->rejected()->create(['user_id' => $userId]);

        $this->actingAs($this->createAdmin());

        $counts = Livewire::test(AdminOnboardingProvidersList::class)
            ->instance()
            ->documentsCountFor($userId);

        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['approved']);
        $this->assertSame(1, $counts['rejected']);
    }

    public function test_approve_onboarding_succeeds_when_all_documents_approved(): void
    {
        $provider = $this->makeInProgressProvider('Approvable Provider');
        $userId = $provider->user_id;

        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $userId]);

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->call('approveOnboarding', $userId)
            ->assertHasNoErrors();

        $provider->refresh();
        $this->assertSame('verified', $provider->verification_status);
        $this->assertSame('active', $provider->status);
        $this->assertNotNull($provider->onboarding_completed_at);
    }

    public function test_approve_onboarding_fails_without_documents(): void
    {
        $provider = $this->makeInProgressProvider('No Docs Provider');
        $userId = $provider->user_id;

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->call('approveOnboarding', $userId)
            ->assertHasErrors('onboarding');

        $provider->refresh();
        $this->assertNotSame('verified', $provider->verification_status);
    }

    public function test_approve_onboarding_fails_with_blocking_documents(): void
    {
        $provider = $this->makeInProgressProvider('Blocked Provider');
        $userId = $provider->user_id;

        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $userId]);
        ProviderOnboardingDocument::factory()->create([
            'user_id' => $userId,
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
        ]);

        $this->actingAs($this->createAdmin());

        Livewire::test(AdminOnboardingProvidersList::class)
            ->call('approveOnboarding', $userId)
            ->assertHasErrors('onboarding');

        $provider->refresh();
        $this->assertNotSame('verified', $provider->verification_status);
    }
}
