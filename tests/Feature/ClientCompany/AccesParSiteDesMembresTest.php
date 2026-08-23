<?php

namespace Tests\Feature\ClientCompany;

use App\Livewire\ClientCompany\MembersAccess;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Enterprise\EnterpriseRoutingService;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Support\Finance\ClientFinanceDocumentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** RESTREINDRE UN MEMBRE À SES LOCAUX — la table dormait depuis sa création. */
class AccesParSiteDesMembresTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: OrganizationMember, 2: OrganizationAccount} */
    private function membre(OrganizationAccount $organisation, string $role = 'site_manager'): array
    {
        $user = User::factory()->create([
            'organization_account_id' => $organisation->id,
            'current_organization_id' => $organisation->id,
            'role' => User::ROLE_ENTREPRISE,
        ]);

        $membre = OrganizationMember::query()->create([
            'organization_account_id' => $organisation->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $membre, $organisation];
    }

    #[Test]
    public function sans_restriction_le_membre_voit_tout(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$user] = $this->membre($organisation);

        OrganizationSite::factory()->count(3)->create(['organization_account_id' => $organisation->id]);

        // LE DÉFAUT EST « TOUT ». Une liste vide veut dire « pas de restriction », jamais « aucun
        // accès » : l'inverse aurait vidé les écrans de toutes les entreprises au premier
        // déploiement.
        $this->assertNull(app(MemberSiteAccessService::class)->sitesAutorises($user));
        $this->assertSame([], app(EnterpriseRoutingService::class)->allowedSiteIdsForUser($user));
    }

    #[Test]
    public function la_restriction_ecrite_est_relue(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$user, $membre] = $this->membre($organisation);

        $sien = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);
        OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);

        app(MemberSiteAccessService::class)->definirLesSites($membre, [$sien->id]);

        $this->assertSame([$sien->id], app(MemberSiteAccessService::class)->sitesAutorises($user->fresh()));

        // La ligne existe VRAIMENT dans la table qui dormait : c'est elle qui doit porter la
        // décision, pas seulement le JSON de repli.
        $this->assertDatabaseHas('organization_member_site_access', [
            'organization_member_id' => $membre->id,
            'organization_site_id' => $sien->id,
        ]);
    }

    #[Test]
    public function la_facture_d_un_local_interdit_disparait(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$user, $membre] = $this->membre($organisation);

        $autorise = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);
        $interdit = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);

        foreach ([$autorise, $interdit] as $site) {
            $booking = Booking::factory()->create([
                'organization_account_id' => $organisation->id,
                'organization_site_id' => $site->id,
            ]);

            FinanceInvoice::factory()->create([
                'organization_account_id' => $organisation->id,
                'rendez_vous_id' => $booking->id,
                'invoice_number' => 'FAC-'.$site->id,
            ]);
        }

        app(MemberSiteAccessService::class)->definirLesSites($membre, [$autorise->id]);

        $visibles = ClientFinanceDocumentScope::apply(FinanceInvoice::query(), $user->fresh())
            ->pluck('invoice_number')
            ->all();

        // C'EST L'ASSERTION QUI COMPTE.
        $this->assertContains('FAC-'.$autorise->id, $visibles);
        $this->assertNotContains('FAC-'.$interdit->id, $visibles);
    }

    #[Test]
    public function lever_la_restriction_rend_tout(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$user, $membre] = $this->membre($organisation);
        $site = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);

        $service = app(MemberSiteAccessService::class);
        $service->definirLesSites($membre, [$site->id]);
        $service->definirLesSites($membre, []);

        $this->assertNull($service->sitesAutorises($user->fresh()));
        $this->assertSame(
            0,
            DB::table('organization_member_site_access')->where('organization_member_id', $membre->id)->count(),
        );
    }

    #[Test]
    public function l_ecran_ecrit_la_restriction(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$responsable] = $this->membre($organisation, 'owner');
        [, $cible] = $this->membre($organisation);

        $site = OrganizationSite::factory()->create(['organization_account_id' => $organisation->id]);

        Livewire::actingAs($responsable)
            ->test(MembersAccess::class)
            ->set('sitesAutorises', [$site->id])
            ->call('enregistrerLesSites', $cible->id);

        $this->assertDatabaseHas('organization_member_site_access', [
            'organization_member_id' => $cible->id,
            'organization_site_id' => $site->id,
        ]);
    }

    #[Test]
    public function on_ne_restreint_pas_a_l_agence_d_une_autre_societe(): void
    {
        $organisation = OrganizationAccount::factory()->create();
        [$responsable] = $this->membre($organisation, 'owner');
        [, $cible] = $this->membre($organisation);

        $voisine = OrganizationAccount::factory()->create();
        $siteVoisin = OrganizationSite::factory()->create(['organization_account_id' => $voisine->id]);

        // Les identifiants viennent du navigateur : sans ce filtre, un identifiant forgé
        // restreindrait un membre à un local qui n'appartient pas à sa société — et révélerait au
        // passage l'existence de ce local.
        Livewire::actingAs($responsable)
            ->test(MembersAccess::class)
            ->set('sitesAutorises', [$siteVoisin->id])
            ->call('enregistrerLesSites', $cible->id);

        $this->assertDatabaseMissing('organization_member_site_access', [
            'organization_member_id' => $cible->id,
            'organization_site_id' => $siteVoisin->id,
        ]);
    }
}
