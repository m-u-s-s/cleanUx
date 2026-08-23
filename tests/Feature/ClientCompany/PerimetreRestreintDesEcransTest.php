<?php

namespace Tests\Feature\ClientCompany;

use App\Livewire\ClientCompany\BookingHub;
use App\Livewire\ClientCompany\ClientCompanyDashboard;
use App\Livewire\ClientCompany\DisputesCenter;
use App\Livewire\ClientCompany\MultiSiteRequest;
use App\Livewire\ClientCompany\SiteManager;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use App\Services\Enterprise\MemberSiteAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** UN RESPONSABLE RESTREINT NE DOIT RIEN VOIR DE L'AGENCE VOISINE — SUR AUCUN ÉCRAN. */
class PerimetreRestreintDesEcransTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private const MARQUEUR_INTERDIT = 'AGENCEINTERDITEZZ';

    private const MARQUEUR_AUTORISE = 'AGENCEAUTORISEEZZ';

    private OrganizationAccount $societe;

    private OrganizationSite $autorise;

    private OrganizationSite $interdit;

    private User $responsable;

    /** @var array<string, mixed> */
    private array $contexte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contexte = $this->createCoverageContext();

        $this->societe = OrganizationAccount::factory()->create(['status' => 'active']);

        $this->autorise = OrganizationSite::factory()->create([
            'organization_account_id' => $this->societe->id,
            'name' => self::MARQUEUR_AUTORISE,
        ]);

        $this->interdit = OrganizationSite::factory()->create([
            'organization_account_id' => $this->societe->id,
            'name' => self::MARQUEUR_INTERDIT,
        ]);

        $this->responsable = User::factory()->create([
            'organization_account_id' => $this->societe->id,
            'current_organization_id' => $this->societe->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        $membre = OrganizationMember::query()->create([
            'organization_account_id' => $this->societe->id,
            'user_id' => $this->responsable->id,
            'role' => 'site_manager',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->reservationPour($this->autorise);
        $this->reservationPour($this->interdit);

        app(MemberSiteAccessService::class)->definirLesSites($membre, [$this->autorise->id]);

        $this->responsable = $this->responsable->fresh();
    }

    private function reservationPour(OrganizationSite $site): Booking
    {
        return Booking::factory()
            ->forStructuredContext(
                $this->contexte['service'],
                $this->contexte['zone'],
                $this->contexte['postalCode'],
            )
            ->create([
                'organization_account_id' => $this->societe->id,
                'customer_organization_id' => $this->societe->id,
                'organization_site_id' => $site->id,
                'status' => 'confirme',
                'adresse' => $site->name.' rue de la Loi',
                'date' => now()->addDay()->toDateString(),
            ]);
    }

    /**
     * @return array<int, array{0: string, 1: class-string}>
     */
    public static function ecrans(): array
    {
        return [
            'tableau de bord' => ['tableau de bord', ClientCompanyDashboard::class],
            'réservations' => ['réservations', BookingHub::class],
            'locaux' => ['locaux', SiteManager::class],
            'litiges' => ['litiges', DisputesCenter::class],
            'demande multi-sites' => ['demande multi-sites', MultiSiteRequest::class],
        ];
    }

    /**
     * @param  class-string  $composant
     */
    #[Test]
    #[DataProvider('ecrans')]
    public function l_agence_interdite_n_apparait_sur_aucun_ecran(string $libelle, string $composant): void
    {
        Livewire::actingAs($this->responsable)
            ->test($composant)
            ->assertDontSee(self::MARQUEUR_INTERDIT, escape: false);
    }

    /** FILTRER LA LISTE NE GARDE RIEN — c'est l'ÉCRITURE qui doit refuser. */
    #[Test]
    public function un_identifiant_de_local_force_ne_cree_aucune_reservation(): void
    {
        $metier = Trade::query()->first()
            ?? Trade::factory()->create();

        $avant = Booking::query()->where('organization_site_id', $this->interdit->id)->count();

        Livewire::actingAs($this->responsable)
            ->test(MultiSiteRequest::class)
            ->set('siteIds', [$this->interdit->id])
            ->set('tradeId', $metier->id)
            ->set('date', now()->addWeek()->toDateString())
            ->call('creer')
            ->assertHasErrors('siteIds');

        $this->assertSame(
            $avant,
            Booking::query()->where('organization_site_id', $this->interdit->id)->count(),
            'Une réservation a été créée sur un local hors du périmètre du demandeur.',
        );
    }

    /** ET LA RESTRICTION NE DOIT PAS TOUT COUPER. */
    #[Test]
    public function l_agence_autorisee_reste_visible(): void
    {
        Livewire::actingAs($this->responsable)
            ->test(SiteManager::class)
            ->assertSee(self::MARQUEUR_AUTORISE, escape: false);
    }

    /** SANS RESTRICTION, ON VOIT LES DEUX — sans quoi le test ci-dessus pourrait passer pour une raison qui n'a rien à voir avec le périmètre (un écran vide, un filtre par défaut, une fixture mal construite). */
    #[Test]
    public function sans_restriction_les_deux_agences_apparaissent(): void
    {
        $libre = User::factory()->create([
            'organization_account_id' => $this->societe->id,
            'current_organization_id' => $this->societe->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        OrganizationMember::query()->create([
            'organization_account_id' => $this->societe->id,
            'user_id' => $libre->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Livewire::actingAs($libre->fresh())
            ->test(SiteManager::class)
            ->assertSee(self::MARQUEUR_AUTORISE, escape: false)
            ->assertSee(self::MARQUEUR_INTERDIT, escape: false);
    }
}
