<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Providers\ProviderRegistrationsCenter;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Écran d'approbation des prestataires inscrits en libre-service depuis l'app mobile.
 *
 * Ces comptes sont créés avec `status = pending` et `self_registered_at` renseigné ; le
 * middleware provider.approved les cantonne à leur dossier d'onboarding tant qu'un humain ne
 * les a pas approuvés. Sans cet écran, la bascule se faisait à la main en base.
 *
 * Distinct d'AdminOnboardingProvidersList, qui traite l'approbation FINALE sur pièces
 * justificatives et refuse tout profil sans document — donc précisément ces nouveaux comptes.
 */
class ProviderRegistrationsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_center_lists_self_registered_accounts_awaiting_approval(): void
    {
        $this->selfRegistered('Alice Indépendante', 'alice@presta.test');

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->assertSee('Alice Indépendante')
            ->assertSee('alice@presta.test');
    }

    /**
     * Le périmètre est la clé de cet écran : les prestataires antérieurs à l'inscription en
     * libre-service n'ont pas à y apparaître, ils ne sont soumis à aucune attente d'approbation.
     */
    public function test_providers_created_before_self_registration_are_not_listed(): void
    {
        $user = User::factory()->employe()->create(['name' => 'Ancien Prestataire']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->assertDontSee('Ancien Prestataire');
    }

    public function test_approving_an_independent_opens_the_full_provider_surface(): void
    {
        $profile = $this->selfRegistered('Alice Indépendante', 'alice@presta.test');

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('approve', $profile->id);

        $this->assertSame('active', $profile->fresh()->status);

        // Preuve par l'usage : la garde provider.approved laisse désormais passer.
        $this->actingAs($profile->user, 'sanctum')
            ->getJson('/api/provider/wallet/balance')
            ->assertOk();
    }

    /**
     * Une société inscrite crée AUSSI un OrganizationAccount en `pending`. L'approuver sans
     * l'activer laisserait l'organisation — celle que consomme l'espace provider-company — dans
     * un état intermédiaire que plus personne ne viendrait débloquer.
     */
    public function test_approving_a_company_also_activates_its_organization(): void
    {
        [$profile, $organization] = $this->selfRegisteredCompany('Nettoyage Dupont SPRL');

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('approve', $profile->id);

        $this->assertSame('active', $profile->fresh()->status);
        $this->assertSame('active', $organization->fresh()->status);
    }

    public function test_rejecting_keeps_the_account_out_of_the_provider_surface(): void
    {
        $profile = $this->selfRegistered('Bob Douteux', 'bob@presta.test');

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->call('reject', $profile->id);

        $this->assertSame('rejected', $profile->fresh()->status);

        $this->actingAs($profile->user, 'sanctum')
            ->getJson('/api/provider/wallet/balance')
            ->assertForbidden();
    }

    public function test_the_pending_filter_hides_accounts_already_handled(): void
    {
        $approved = $this->selfRegistered('Déjà Validé', 'valide@presta.test');
        $approved->forceFill(['status' => 'active'])->save();
        $this->selfRegistered('Encore En Attente', 'attente@presta.test');

        Livewire::actingAs($this->admin())
            ->test(ProviderRegistrationsCenter::class)
            ->assertSee('Encore En Attente')
            ->assertDontSee('Déjà Validé');
    }

    public function test_a_non_admin_cannot_reach_the_center(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'client']))
            ->get('/admin/inscriptions-prestataires')
            ->assertForbidden();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function selfRegistered(string $name, string $email): ProviderProfile
    {
        $user = User::factory()->employe()->create(['name' => $name, 'email' => $email]);

        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);
        $profile->forceFill(['self_registered_at' => now()])->save();

        return $profile;
    }

    /** @return array{0: ProviderProfile, 1: OrganizationAccount} */
    private function selfRegisteredCompany(string $companyName): array
    {
        $user = User::factory()->employe()->create(['name' => 'Gérant Dupont']);

        $organization = OrganizationAccount::create([
            'name' => $companyName,
            'legal_name' => $companyName,
            'slug' => 'nettoyage-dupont-sprl',
            'type' => 'provider_company',
            'status' => 'pending',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $organization->id,
            'provider_type' => 'company',
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);
        $profile->forceFill(['self_registered_at' => now()])->save();

        return [$profile, $organization];
    }
}
