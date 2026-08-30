<?php

namespace Tests\Feature\Navigation;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UN SEUL ESPACE, PAS DEUX.
 *
 * Un compte rattache a une societe avait son espace personnel ET celui de sa societe, chacun
 * avec sa barre et son repertoire. Les ecrans personnels vivent desormais sous le prefixe de
 * la societe ; les anciennes adresses y renvoient, et un compte SANS societe ne bouge pas.
 */
class UnSeulEspacePourUneSocieteTest extends TestCase
{
    use RefreshDatabase;

    private function membreDeSocieteCliente(): User
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $user = User::factory()->entreprise()->create();
        $user->forceFill([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ])->save();

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function membreDeSocietePrestataire(): User
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return $user->fresh();
    }

    public function test_l_accueil_personnel_renvoie_a_celui_de_la_societe(): void
    {
        $this->actingAs($this->membreDeSocieteCliente())
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client-company.dashboard'));
    }

    public function test_un_ecran_personnel_renvoie_a_sa_jumelle_fusionnee(): void
    {
        $this->actingAs($this->membreDeSocieteCliente())
            ->get(route('client.finance'))
            ->assertRedirect(route('client-company.perso.finance'));
    }

    public function test_l_ecran_fusionne_porte_la_barre_de_la_societe(): void
    {
        $reponse = $this->actingAs($this->membreDeSocieteCliente())
            ->get(route('client-company.perso.finance'))
            ->assertOk();

        /*
         * LA BARRE EST DESORMAIS LA MEME PARTOUT : ce sont ses LIENS qui disent l'espace,
         * plus sa classe CSS. `brio-barre` a longtemps servi de discriminant ; il ne prouve
         * plus rien depuis que les espaces societe montent `navigation-menu`.
         */
        $reponse->assertSee(route('client-company.modules'), escape: false);
        $reponse->assertDontSee(route('client.modules'), escape: false);
    }

    public function test_le_repertoire_de_la_societe_liste_les_ecrans_personnels(): void
    {
        $this->actingAs($this->membreDeSocieteCliente())
            ->get(route('client-company.modules'))
            ->assertOk()
            ->assertSee('Portefeuille', escape: false)
            ->assertSee('Parrainage', escape: false)
            // TEMOIN — les modules de la societe sont toujours la.
            ->assertSee('Facturation', escape: false);
    }

    public function test_cote_prestataire_l_ecran_personnel_renvoie_aussi(): void
    {
        $this->actingAs($this->membreDeSocietePrestataire())
            ->get(route('employe.missions'))
            ->assertRedirect(route('provider-company.perso.missions'));
    }

    /**
     * TEMOIN — un compte SANS societe garde son espace personnel. Sans lui, la redirection
     * pourrait s'appliquer a tout le monde et priver un particulier de son seul espace.
     */
    public function test_un_client_sans_societe_n_est_jamais_renvoye(): void
    {
        $client = User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        $reponse = $this->actingAs($client)->get(route('client.finance'))->assertOk();

        // Sa barre reste la personnelle : il n'a pas d'espace societe ou aller.
        $reponse->assertSee(route('client.modules'), escape: false);
        $reponse->assertDontSee(route('client-company.modules'), escape: false);
    }

    /** Les ecrans fusionnes sont gardes par le type d'organisation, comme le reste de l'espace. */
    public function test_un_client_sans_societe_n_entre_pas_dans_l_espace_fusionne(): void
    {
        $client = User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        $this->actingAs($client)
            ->get(route('client-company.perso.finance'))
            ->assertForbidden();
    }

    /** La porte « Espace entreprise » n'a plus d'objet : elle designait le second espace. */
    public function test_plus_aucune_porte_vers_un_second_espace(): void
    {
        $cles = ModuleCatalogue::catalogueComplet()->pluck('label');

        $this->assertNotContains('Espace entreprise', $cles->all());
    }

    /** Chaque case de l'espace societe mene a une route de CET espace, sans redirection. */
    public function test_aucune_case_de_l_espace_societe_ne_passe_par_une_redirection(): void
    {
        $fuites = [];

        foreach (['client-company' => 'client.', 'provider-company' => 'employe.'] as $societe => $prefixe) {
            foreach (ModuleCatalogue::catalogueComplet()->where('context', $societe) as $module) {
                // Une route personnelle SANS jumelle reste legitime : elle est declaree ailleurs.
                $jumelle = $societe.'.perso.'.substr($module['route'], strlen($prefixe));

                if (str_starts_with($module['route'], $prefixe) && Route::has($jumelle)) {
                    $fuites[] = $module['route'];
                }
            }
        }

        $this->assertSame([], $fuites, 'Ces cases renvoient vers l’espace personnel : '.implode(', ', $fuites));
    }
}
