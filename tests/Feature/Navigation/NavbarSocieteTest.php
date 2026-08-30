<?php

namespace Tests\Feature\Navigation;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES ESPACES SOCIÉTÉ PORTENT LA BARRE DES AUTRES TABLEAUX DE BORD.
 *
 * `x-barre-societe` en était une seconde définition, moitié moins fournie : pas de menu mobile,
 * pas d'aperçu de notifications, pas de menu de compte. Les deux espaces société montent
 * désormais `navigation-menu`, comme le client particulier, le prestataire et l'administration.
 */
class NavbarSocieteTest extends TestCase
{
    use RefreshDatabase;

    private function barre(): string
    {
        return (string) file_get_contents(resource_path('views/navigation-menu.blade.php'));
    }

    private function patronneDeSocieteCliente(): User
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

    private function patronDeSocietePrestataire(): User
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

    public function test_la_barre_ne_declare_plus_ses_liens_en_dur(): void
    {
        $ecarts = [];
        $source = $this->barre();

        if (str_contains($source, "'label' =>")) {
            $ecarts[] = 'une liste d entrees ecrite en dur subsiste';
        }

        if (! str_contains($source, 'ModuleCatalogue')) {
            $ecarts[] = 'la barre ne passe pas par ModuleCatalogue';
        }

        $this->assertSame([], $ecarts, 'La barre ne tire pas ses liens du catalogue.');
    }

    /**
     * UNE SEULE DEFINITION POUR TOUS LES ESPACES. Les gabarits société portaient chacun sa
     * copie, puis une barre partagée qui restait quand même la deuxième du produit.
     */
    public function test_les_gabarits_montent_la_barre_partagee(): void
    {
        $sansBarre = [];

        foreach (['client-company', 'provider-company', 'app'] as $gabarit) {
            $source = (string) file_get_contents(resource_path("views/layouts/{$gabarit}.blade.php"));

            if (! str_contains($source, "@livewire('navigation-menu')")) {
                $sansBarre[] = $gabarit;
            }

            // Une barre ecrite a nouveau dans le gabarit ferait revivre la divergence.
            if (str_contains($source, '<nav')) {
                $sansBarre[] = "{$gabarit} : une barre en dur subsiste";
            }
        }

        $this->assertSame([], $sansBarre, 'Ces gabarits ne montent pas la barre partagee.');
    }

    /**
     * Sans cette porte, les modules non-principaux de ces espaces seraient injoignables :
     * la barre est la seule surface permanente qu'ils possedent.
     */
    public function test_la_barre_mene_a_la_page_modules_de_son_espace(): void
    {
        $this->assertStringContainsString('routeDesModules', $this->barre());
    }

    /**
     * L'ESPACE PASSE AVANT LE RÔLE. Une patronne de société cliente est aussi `isClient()` :
     * le rôle seul lui rendait la barre du particulier, avec ses liens et sa page Modules.
     */
    public function test_le_tableau_de_bord_client_porte_les_liens_de_sa_societe(): void
    {
        $reponse = $this->actingAs($this->patronneDeSocieteCliente())
            ->get(route('client-company.dashboard'))
            ->assertOk();

        // La barre partagee — la meme classe que sur les autres tableaux de bord.
        $reponse->assertSee('brio-barre', escape: false)
            ->assertSee(route('client-company.modules'), escape: false)
            ->assertDontSee(route('client.modules'), escape: false);

        /*
         * L'APPEL À L'ACTION SUIT L'ESPACE. Une patronne de société est `isClient()` : sans
         * garde, la barre l'invitait à réserver POUR ELLE-MÊME depuis l'écran de sa société.
         */
        $reponse->assertSee(route('client-company.bookings.create'), escape: false)
            ->assertDontSee(route('client.rendezvous.create'), escape: false);
    }

    public function test_le_tableau_de_bord_prestataire_porte_les_liens_de_sa_societe(): void
    {
        $reponse = $this->actingAs($this->patronDeSocietePrestataire())
            ->get(route('provider-company.dashboard'))
            ->assertOk();

        $reponse->assertSee('brio-barre', escape: false)
            ->assertSee(route('provider-company.modules'), escape: false)
            ->assertDontSee(route('employe.modules'), escape: false);
    }

    /**
     * TEMOIN DE L'APPEL À L'ACTION — un client SANS société garde son « Réserver ». Sans lui,
     * l'assertion d'absence ci-dessus passerait au vert sur un bouton disparu pour tout le monde.
     */
    public function test_temoin_un_client_particulier_garde_son_bouton_reserver(): void
    {
        $client = User::factory()->client()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee(route('client.rendezvous.create'), escape: false);
    }

    /**
     * TEMOIN — le menu de compte et le menu mobile, absents de l'ancienne barre société,
     * atteignent bien ces deux espaces. Sans lui, les tests ci-dessus resteraient verts
     * sur une barre reduite a ses seuls liens.
     */
    public function test_la_barre_des_espaces_societe_porte_le_menu_de_compte(): void
    {
        foreach ([
            ['user' => $this->patronneDeSocieteCliente(), 'route' => 'client-company.dashboard'],
            ['user' => $this->patronDeSocietePrestataire(), 'route' => 'provider-company.dashboard'],
        ] as $cas) {
            $this->actingAs($cas['user'])
                ->get(route($cas['route']))
                ->assertOk()
                ->assertSee('menu-mobile', escape: false)
                ->assertSee(route('logout'), escape: false);
        }
    }
}
