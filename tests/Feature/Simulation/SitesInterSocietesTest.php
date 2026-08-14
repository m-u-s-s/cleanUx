<?php

namespace Tests\Feature\Simulation;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Livewire\ClientCompany\BookingHub;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Missions\MissionAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * UN SITE D'UNE SOCIÉTÉ, UNE INTERVENTION D'UNE AUTRE.
 *
 * C'est la relation B2B complète, et elle met en présence trois périmètres qui ne doivent jamais se
 * confondre : la société CLIENTE, qui possède le local ; la société PRESTATAIRE, qui exécute ; le
 * TRAVAILLEUR, qui s'y rend. Chacun doit voir ce qui le concerne, et rien de plus.
 *
 * LES DEUX ERREURS OPPOSÉES SE LISENT AU MÊME ENDROIT. Si la société cliente ne voit pas
 * l'intervention sur son site, elle ne peut ni la suivre ni la contester. Si la société prestataire
 * — ou pire, une société tierce — voit le local d'un client qui n'est pas le sien, on a exposé une
 * adresse d'entreprise et le nom de son responsable.
 *
 * Ce parcours suit une intervention du bon de commande jusqu'à l'exécution, en interrogeant à chaque
 * étape les surfaces réelles : l'écran de la société cliente et l'API de l'application prestataire.
 */
class SitesInterSocietesTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $contexte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();
    }

    private function societe(OrganizationType $type): OrganizationAccount
    {
        return OrganizationAccount::factory()->create([
            'type' => $type->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationAccount $societe, OrganizationRole $role, string $roleUtilisateur): User
    {
        $user = User::factory()->create([
            'role' => $roleUtilisateur,
            'organization_account_id' => $societe->id,
            'current_organization_id' => $societe->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $societe->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * Une intervention commandée par la société cliente pour l'un de ses locaux, confiée à la
     * société prestataire.
     */
    private function intervention(
        OrganizationAccount $cliente,
        OrganizationSite $site,
        OrganizationAccount $prestataire,
        User $donneurDOrdre,
    ): Booking {
        return Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create([
                'client_id' => $donneurDOrdre->id,
                'customer_user_id' => $donneurDOrdre->id,
                'organization_account_id' => $cliente->id,
                'customer_organization_id' => $cliente->id,
                'organization_site_id' => $site->id,
                // C'est la DÉCISION de confier le travail à cette société-là :
                // `MissionFromRendezVousSyncService` la reporte sur la mission.
                'assigned_provider_organization_id' => $prestataire->id,
                'status' => 'confirme',
                'date' => now()->addDay()->toDateString(),
                'heure' => '09:00:00',
                'devis_estime' => 240,
            ]);
    }

    /** Les identifiants de mission que cette personne voit dans l'application prestataire. */
    private function saListe(User $user): array
    {
        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/provider/missions/active');
        $reponse->assertOk();

        return collect($reponse->json('data'))->pluck('id')->sort()->values()->all();
    }

    /**
     * LE PARCOURS COMPLET : la commande d'un site atterrit chez le bon travailleur.
     */
    public function test_une_intervention_sur_site_arrive_au_travailleur_de_la_societe_prestataire(): void
    {
        $cliente = $this->societe(OrganizationType::CLIENT_COMPANY);
        $prestataire = $this->societe(OrganizationType::PROVIDER_COMPANY);

        $donneurDOrdre = $this->membre($cliente, OrganizationRole::OWNER, User::ROLE_ENTREPRISE);
        $travailleur = $this->membre($prestataire, OrganizationRole::WORKER, User::ROLE_EMPLOYE);

        $site = OrganizationSite::factory()->create(['organization_account_id' => $cliente->id]);
        $reservation = $this->intervention($cliente, $site, $prestataire, $donneurDOrdre);

        // La mission naît du rendez-vous confirmé, et porte la société exécutante.
        $mission = $reservation->missions()->latest('id')->first();
        $this->assertNotNull($mission, 'Aucune mission n’a été créée pour l’intervention de site.');
        $this->assertSame(
            $prestataire->id,
            (int) $mission->provider_organization_id,
            'La mission n’appartient pas à la société prestataire désignée.',
        );

        // La société prestataire désigne l'un de ses travailleurs.
        $membre = OrganizationMember::query()
            ->where('organization_account_id', $prestataire->id)
            ->where('user_id', $travailleur->id)
            ->firstOrFail();

        app(MissionAssignmentService::class)->assigner($mission, $membre);

        $this->assertSame([$mission->id], $this->saListe($travailleur));

        $this->assertSame(
            $travailleur->id,
            $reservation->fresh()->intervenantId(),
            'L’intervenant de l’intervention de site n’est pas le travailleur désigné.',
        );
    }

    /**
     * LA SOCIÉTÉ CLIENTE SUIT L'INTERVENTION SUR SON LOCAL.
     *
     * C'est ce pour quoi elle a signé : savoir ce qui se passe chez elle, local par local.
     */
    public function test_la_societe_cliente_voit_l_intervention_sur_son_local(): void
    {
        $cliente = $this->societe(OrganizationType::CLIENT_COMPANY);
        $prestataire = $this->societe(OrganizationType::PROVIDER_COMPANY);

        $donneurDOrdre = $this->membre($cliente, OrganizationRole::OWNER, User::ROLE_ENTREPRISE);
        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $cliente->id,
            'name' => 'SITEDUCLIENTZZ',
        ]);

        $this->intervention($cliente, $site, $prestataire, $donneurDOrdre);

        Livewire::actingAs($donneurDOrdre)
            ->test(BookingHub::class)
            ->assertSee('SITEDUCLIENTZZ', escape: false);
    }

    /**
     * L'ÉTANCHÉITÉ : une société prestataire tierce ne voit rien du local d'un client qui n'est pas
     * le sien.
     *
     * Ce n'est pas une question de confort d'affichage : ce qui fuiterait, c'est l'adresse d'un site
     * d'entreprise, l'horaire d'intervention et le nom du responsable sur place.
     */
    public function test_une_societe_prestataire_tierce_ne_voit_rien_du_local(): void
    {
        $cliente = $this->societe(OrganizationType::CLIENT_COMPANY);
        $prestataire = $this->societe(OrganizationType::PROVIDER_COMPANY);
        $tierce = $this->societe(OrganizationType::PROVIDER_COMPANY);

        $donneurDOrdre = $this->membre($cliente, OrganizationRole::OWNER, User::ROLE_ENTREPRISE);
        $travailleur = $this->membre($prestataire, OrganizationRole::WORKER, User::ROLE_EMPLOYE);
        $intrus = $this->membre($tierce, OrganizationRole::WORKER, User::ROLE_EMPLOYE);

        $site = OrganizationSite::factory()->create(['organization_account_id' => $cliente->id]);
        $reservation = $this->intervention($cliente, $site, $prestataire, $donneurDOrdre);

        $mission = $reservation->missions()->latest('id')->firstOrFail();
        $membre = OrganizationMember::query()
            ->where('organization_account_id', $prestataire->id)
            ->where('user_id', $travailleur->id)
            ->firstOrFail();

        app(MissionAssignmentService::class)->assigner($mission, $membre);

        $this->assertSame([], $this->saListe($intrus));

        // Et le détail est REFUSÉ, pas seulement absent de la liste : une adresse ne se protège pas
        // en la cachant d'un écran si l'URL la rend quand même.
        $this->actingAs($intrus, 'sanctum')
            ->getJson("/api/provider/missions/{$mission->id}")
            ->assertForbidden();
    }

    /**
     * ET UNE AUTRE SOCIÉTÉ CLIENTE NE VOIT PAS LE LOCAL DU VOISIN.
     */
    public function test_une_autre_societe_cliente_ne_voit_pas_le_local(): void
    {
        $cliente = $this->societe(OrganizationType::CLIENT_COMPANY);
        $voisine = $this->societe(OrganizationType::CLIENT_COMPANY);
        $prestataire = $this->societe(OrganizationType::PROVIDER_COMPANY);

        $donneurDOrdre = $this->membre($cliente, OrganizationRole::OWNER, User::ROLE_ENTREPRISE);
        $curieux = $this->membre($voisine, OrganizationRole::OWNER, User::ROLE_ENTREPRISE);

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $cliente->id,
            'name' => 'SITEDUVOISINZZ',
        ]);

        $this->intervention($cliente, $site, $prestataire, $donneurDOrdre);

        Livewire::actingAs($curieux)
            ->test(BookingHub::class)
            ->assertDontSee('SITEDUVOISINZZ', escape: false);
    }
}
