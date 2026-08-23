<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LA BARRE DU BAS N'APPARTIENT NI AU CLIENT, NI À L'APPLICATION NATIVE. */
class GabaritSocietePrestataireBarreDuBasTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);

        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    public function test_l_espace_prestataire_ne_propose_pas_de_reserver_une_prestation(): void
    {
        $reponse = $this->actingAs($this->patron())->get(route('provider-company.planning'));

        $reponse->assertOk();
        $reponse->assertDontSee('dashboard/client/rendez-vous');
        $reponse->assertDontSee('Mes RDV');
    }

    public function test_la_page_garde_sa_propre_navigation_quand_elle_n_est_pas_embarquee(): void
    {
        // TÉMOIN POSITIF.
        $reponse = $this->actingAs($this->patron())->get(route('provider-company.planning'));

        $reponse->assertOk();
        $reponse->assertSee('data-chrome="primary-nav"', false);
    }

    public function test_le_mode_embarque_retire_tout_le_chrome_web(): void
    {
        $reponse = $this->actingAs($this->patron())->get(route('provider-company.planning', ['embed' => 1]));

        $reponse->assertOk();
        // Ni la navigation haute (déjà protégée), ni la barre du bas (c'est la correction).
        $reponse->assertDontSee('data-chrome="primary-nav"', false);
        $reponse->assertDontSee('aria-label="Navigation mobile"', false);
    }
}
