<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'espace societe prestataire recopiait le systeme en Tailwind brut : en-tete maison, cases
 * d'indicateur maison, etat vide maison. Il emploie desormais les memes composants que les
 * autres tableaux de bord, et sa barre porte le theme, la langue et les notifications.
 */
class LEspaceSocieteSuitLeSystemeTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $patron->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $patron->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return $patron->fresh();
    }

    public function test_la_page_emploie_la_coquille_du_systeme(): void
    {
        Livewire::actingAs($this->patron())->test(ProviderDashboard::class)
            ->assertOk()
            ->assertSee('brio-hero', escape: false);
    }

    public function test_les_indicateurs_emploient_la_case_du_systeme(): void
    {
        Livewire::actingAs($this->patron())->test(ProviderDashboard::class)
            ->assertSee('brio-stat', escape: false);
    }

    public function test_les_blocs_emploient_la_carte_du_systeme(): void
    {
        Livewire::actingAs($this->patron())->test(ProviderDashboard::class)
            ->assertSee('brio-card', escape: false);
    }

    public function test_l_etat_vide_emploie_celui_du_systeme(): void
    {
        Livewire::actingAs($this->patron())->test(ProviderDashboard::class)
            ->assertSee('brio-empty', escape: false);
    }

    /**
     * TEMOIN — les donnees de l'ecran sont toujours la. Sans lui, les tests ci-dessus
     * resteraient verts sur une page reduite a une coquille vide.
     */
    public function test_temoin_l_ecran_dit_toujours_ce_qu_il_disait(): void
    {
        $patron = $this->patron();

        Livewire::actingAs($patron)->test(ProviderDashboard::class)
            ->assertSee($patron->currentOrganization->name)
            ->assertSee('Missions du jour', escape: false)
            ->assertSee("Missions aujourd'hui");
    }

    /** La barre du haut porte ce que portent les autres espaces. */
    public function test_la_barre_porte_le_theme_la_langue_et_les_notifications(): void
    {
        $reponse = $this->actingAs($this->patron())->get(route('provider-company.dashboard'));

        $reponse->assertOk()
            ->assertSee('Changer le thème', escape: false)
            ->assertSee(route('locale.switch'), escape: false)
            ->assertSee('data-cloche-compteur', escape: false);
    }
}
