<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\Booking;
use App\Models\FinanceInvoice;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** L'API DE L'ESPACE SOCIÉTÉ CLIENTE. POURQUOI CE FICHIER EXISTE. */
class ClientCompanyApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvec(OrganizationRole $role): array
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $user];
    }

    // ──────────────────────────────────────────────────────
    // Accueil
    // ──────────────────────────────────────────────────────

    #[Test]
    public function l_accueil_compte_ce_qui_appartient_a_la_societe(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        OrganizationSite::factory()->count(2)->create([
            'organization_account_id' => $org->id,
            'status' => 'active',
        ]);
        Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/overview')
            ->assertOk()
            ->assertJsonPath('data.kpis.sites_count', 2)
            ->assertJsonPath('data.kpis.bookings_active', 1)
            ->assertJsonPath('data.kpis.members_count', 1);
    }

    #[Test]
    public function l_accueil_ne_compte_pas_les_locaux_d_une_autre_societe(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->clientCompany()->create();
        OrganizationSite::factory()->count(5)->create([
            'organization_account_id' => $concurrente->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/overview')
            ->assertOk()
            ->assertJsonPath('data.kpis.sites_count', 0);
    }

    // ──────────────────────────────────────────────────────
    // Locaux
    // ──────────────────────────────────────────────────────

    #[Test]
    public function les_locaux_de_la_societe_sont_listes(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Siège Bruxelles',
            'city' => 'Bruxelles',
            'status' => 'active',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/sites')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Siège Bruxelles')
            ->assertJsonPath('data.0.city', 'Bruxelles');
    }

    #[Test]
    public function un_local_d_une_autre_societe_n_est_jamais_liste(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->clientCompany()->create();
        OrganizationSite::factory()->create([
            'organization_account_id' => $concurrente->id,
            'name' => 'Local confidentiel',
            'status' => 'active',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/sites')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Local confidentiel']);
    }

    #[Test]
    public function lister_les_locaux_exige_la_permission_de_les_voir(): void
    {
        // Le rôle FINANCE voit les factures et les réservations, pas le parc immobilier.
        // C'est la règle que `SiteManager` applique déjà côté web (abort 403 sur sites.view_all).
        [, $comptable] = $this->societeAvec(OrganizationRole::FINANCE);

        Sanctum::actingAs($comptable, ['*']);

        $this->getJson('/api/client/company/sites')->assertForbidden();
    }

    #[Test]
    public function un_local_est_cree_et_rattache_a_la_societe_de_l_appelant(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        Sanctum::actingAs($patron, ['*']);

        $this->postJson('/api/client/company/sites', [
            'name' => 'Entrepôt Anderlecht',
            'address' => 'Rue du Test 1',
            'city' => 'Anderlecht',
            'postal_code' => '1070',
        ])->assertCreated();

        $this->assertDatabaseHas('organization_sites', [
            'organization_account_id' => $org->id,
            'name' => 'Entrepôt Anderlecht',
        ]);
    }

    #[Test]
    public function creer_un_local_exige_la_permission_et_pas_la_seule_appartenance(): void
    {
        // REQUESTER peut demander une intervention ; il ne redessine pas le parc.
        [, $demandeur] = $this->societeAvec(OrganizationRole::REQUESTER);

        Sanctum::actingAs($demandeur, ['*']);

        $this->postJson('/api/client/company/sites', ['name' => 'Local pirate'])
            ->assertForbidden();

        $this->assertDatabaseMissing('organization_sites', ['name' => 'Local pirate']);
    }

    // ──────────────────────────────────────────────────────
    // Réservations
    // ──────────────────────────────────────────────────────

    #[Test]
    public function les_reservations_de_la_societe_sont_listees_avec_leur_local(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Siège Bruxelles',
            'status' => 'active',
        ]);
        Booking::factory()->create([
            'customer_organization_id' => $org->id,
            'organization_site_id' => $site->id,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/bookings')
            ->assertOk()
            ->assertJsonPath('data.0.site', 'Siège Bruxelles')
            ->assertJsonPath('data.0.status', 'confirmed');
    }

    #[Test]
    public function la_reservation_d_une_autre_societe_n_est_jamais_listee(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->clientCompany()->create();
        $espionne = Booking::factory()->create([
            'customer_organization_id' => $concurrente->id,
            'status' => 'confirmed',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/client/company/bookings')->assertOk();

        $this->assertNotContains($espionne->id, array_column($reponse->json('data'), 'id'));
    }

    // ──────────────────────────────────────────────────────
    // Membres
    // ──────────────────────────────────────────────────────

    #[Test]
    public function les_membres_de_la_societe_sont_listes(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $collegue = User::factory()->create(['name' => 'Camille Dupont']);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $collegue->id,
            'role' => OrganizationRole::REQUESTER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/members')
            ->assertOk()
            ->assertJsonPath('data.1.name', 'Camille Dupont')
            ->assertJsonPath('data.1.role', OrganizationRole::REQUESTER->value);
    }

    #[Test]
    public function le_membre_d_une_autre_societe_n_est_jamais_liste(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->clientCompany()->create();
        $etranger = User::factory()->create(['name' => 'Intrus Anonyme']);
        OrganizationMember::create([
            'organization_account_id' => $concurrente->id,
            'user_id' => $etranger->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/members')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Intrus Anonyme']);
    }

    // ──────────────────────────────────────────────────────
    // Contrats
    // ──────────────────────────────────────────────────────

    #[Test]
    public function les_contrats_de_la_societe_sont_listes(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        OrganizationContract::factory()->create([
            'organization_account_id' => $org->id,
            'contract_reference' => 'CTR-2026-001',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/contracts')
            ->assertOk()
            ->assertJsonPath('data.0.reference', 'CTR-2026-001');
    }

    #[Test]
    public function le_contrat_d_une_autre_societe_n_est_jamais_liste(): void
    {
        [, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        $concurrente = OrganizationAccount::factory()->clientCompany()->create();
        OrganizationContract::factory()->create([
            'organization_account_id' => $concurrente->id,
            'contract_reference' => 'CTR-SECRET',
        ]);

        Sanctum::actingAs($patron, ['*']);

        $this->getJson('/api/client/company/contracts')
            ->assertOk()
            ->assertJsonMissing(['reference' => 'CTR-SECRET']);
    }

    // ──────────────────────────────────────────────────────
    // Facturation
    // ──────────────────────────────────────────────────────

    #[Test]
    public function la_facturation_rend_de_vraies_factures_et_pas_des_zeros(): void
    {
        [$org, $patron] = $this->societeAvec(OrganizationRole::OWNER);

        FinanceInvoice::factory()->create([
            'organization_account_id' => $org->id,
            'client_id' => $patron->id,
            'invoice_number' => 'F-2026-0042',
            'status' => 'issued',
            'total_amount' => 250.00,
            'balance_due' => 250.00,
        ]);

        Sanctum::actingAs($patron, ['*']);

        $reponse = $this->getJson('/api/client/company/billing')->assertOk();

        $reponse->assertJsonPath('data.invoices.0.invoice_number', 'F-2026-0042');
        // Le stub web renvoyait 0 quoi qu'il arrive : ce montant est la preuve qu'on ne le recopie pas.
        $this->assertSame(250.0, (float) $reponse->json('data.summary.unpaid'));
    }

    #[Test]
    public function consulter_la_facturation_exige_la_permission_finance(): void
    {
        [, $demandeur] = $this->societeAvec(OrganizationRole::REQUESTER);

        Sanctum::actingAs($demandeur, ['*']);

        $this->getJson('/api/client/company/billing')->assertForbidden();
    }

    // ──────────────────────────────────────────────────────
    // La garde commune
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_compte_tel_que_db_seed_le_produit_atteint_bien_son_espace(): void
    {
        // LA FORME EXACTE QUE `DemoPlatformSeeder` ÉCRIT, ET ELLE NE PASSAIT PAS.
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $user = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => null,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/client/company/overview')->assertOk();
        $this->getJson('/api/client/company/sites')->assertOk();
        $this->getJson('/api/client/company/billing')->assertOk();
    }

    #[Test]
    public function un_contexte_d_organisation_sans_adhesion_active_ne_donne_rien(): void
    {
        // Durcissement, pas maintien.
        $etrangere = OrganizationAccount::factory()->clientCompany()->create();

        $intrus = User::factory()->create([
            'organization_account_id' => $etrangere->id,
            'current_organization_id' => $etrangere->id,
        ]);
        // Aucune ligne dans `organization_members` : le pointeur existe, l'adhésion non.

        Sanctum::actingAs($intrus, ['*']);

        $this->getJson('/api/client/company/overview')->assertForbidden();
        $this->getJson('/api/client/company/members')->assertForbidden();
    }

    #[Test]
    public function une_adhesion_suspendue_ne_rouvre_pas_l_espace(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $ancien = User::factory()->create([
            'organization_account_id' => $org->id,
            'current_organization_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $ancien->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'suspended',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($ancien, ['*']);

        $this->getJson('/api/client/company/overview')->assertForbidden();
    }

    #[Test]
    public function un_particulier_sans_organisation_active_est_refuse_partout(): void
    {
        $particulier = User::factory()->create([
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        Sanctum::actingAs($particulier, ['*']);

        foreach (['overview', 'sites', 'bookings', 'members', 'contracts', 'billing'] as $route) {
            $this->getJson("/api/client/company/{$route}")
                ->assertForbidden();
        }
    }
}
