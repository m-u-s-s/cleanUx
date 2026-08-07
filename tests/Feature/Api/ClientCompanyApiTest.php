<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\SigningAppointment;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * L'ESPACE CLIENT SOCIÉTÉ N'AVAIT AUCUNE API — SEULEMENT DES ÉCRANS WEB.
 *
 * Vérifié endpoint par endpoint avant d'écrire : `routes/api/client.php` n'expose que
 * `/client/companies` — l'ANNUAIRE des sociétés prestataires à parcourir, sans rapport avec la
 * société de l'appelant — et `/client/bookings`. Ni locaux, ni membres, ni rendez-vous de
 * signature, ni demande multi-locaux.
 *
 * C'est la même situation que côté prestataire il y a quelques jours : l'application cliente ne
 * pouvait rien afficher de la société parce qu'il n'y avait rien à consommer.
 *
 * Les deux règles de garde sont identiques à celles de `Api\Provider\CompanyController` :
 *   1. toute requête est limitée à l'organisation ACTIVE de l'appelant ;
 *   2. toute écriture exige une permission, jamais la seule appartenance.
 *
 * Les services métier sont RÉUTILISÉS — `MultiSiteRequestService` et `SigningAppointmentService`,
 * écrits en phase 1 pour le web. Les réimplémenter ferait diverger les deux surfaces.
 */
class ClientCompanyApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeCliente(OrganizationRole $role = OrganizationRole::OWNER): array
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

    #[Test]
    public function les_locaux_de_la_societe_sont_listes(): void
    {
        [$org, $acteur] = $this->societeCliente();

        OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'name' => 'Siège Lyon',
            'site_code' => 'LYON-01',
        ]);

        Sanctum::actingAs($acteur, ['*']);

        $this->getJson('/api/client/company/sites')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Siège Lyon');
    }

    #[Test]
    public function les_locaux_d_une_autre_societe_restent_invisibles(): void
    {
        [, $acteur] = $this->societeCliente();

        $autreOrg = OrganizationAccount::factory()->clientCompany()->create();
        OrganizationSite::factory()->create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'Local Concurrent',
            'site_code' => 'AUTRUI',
        ]);

        Sanctum::actingAs($acteur, ['*']);

        $this->getJson('/api/client/company/sites')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Local Concurrent']);
    }

    #[Test]
    public function les_membres_de_la_societe_sont_listes(): void
    {
        [$org, $acteur] = $this->societeCliente();

        $collegue = User::factory()->create(['name' => 'Camille Acheteuse']);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $collegue->id,
            'role' => OrganizationRole::REQUESTER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        Sanctum::actingAs($acteur, ['*']);

        $this->getJson('/api/client/company/members')
            ->assertOk()
            ->assertJsonPath('data.1.name', 'Camille Acheteuse');
    }

    #[Test]
    public function une_demande_multi_locaux_cree_une_reservation_par_local(): void
    {
        [$org, $acteur] = $this->societeCliente();

        $trade = Trade::factory()->create();
        $premier = OrganizationSite::factory()->create(['organization_account_id' => $org->id, 'site_code' => 'A']);
        $second = OrganizationSite::factory()->create(['organization_account_id' => $org->id, 'site_code' => 'B']);

        Sanctum::actingAs($acteur, ['*']);

        $this->postJson('/api/client/company/multi-site-request', [
            'site_ids' => [$premier->id, $second->id],
            'trade_id' => $trade->id,
            'scheduled_at' => now()->addWeek()->toIso8601String(),
            'duration_minutes' => 120,
        ])->assertCreated();

        // Une mère porte l'intention commune, chaque local reçoit sa fille.
        $this->assertDatabaseCount('bookings', 3);
    }

    #[Test]
    public function un_role_sans_droit_ne_cree_pas_de_demande(): void
    {
        [$org, $viewer] = $this->societeCliente(OrganizationRole::VIEWER);

        $trade = Trade::factory()->create();
        $site = OrganizationSite::factory()->create(['organization_account_id' => $org->id, 'site_code' => 'A']);

        Sanctum::actingAs($viewer, ['*']);

        $this->postJson('/api/client/company/multi-site-request', [
            'site_ids' => [$site->id],
            'trade_id' => $trade->id,
            'scheduled_at' => now()->addWeek()->toIso8601String(),
        ])->assertForbidden();

        $this->assertDatabaseCount('bookings', 0);
    }

    #[Test]
    public function on_planifie_et_liste_une_signature_sur_place(): void
    {
        [$org, $acteur] = $this->societeCliente();

        $site = OrganizationSite::factory()->create(['organization_account_id' => $org->id, 'site_code' => 'SIEGE']);

        Sanctum::actingAs($acteur, ['*']);

        $this->postJson('/api/client/company/signing-appointments', [
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'organization_site_id' => $site->id,
            'notes' => 'Contrat-cadre au siège.',
        ])->assertCreated();

        $this->getJson('/api/client/company/signing-appointments')
            ->assertOk()
            ->assertJsonPath('data.0.site', $site->name);

        $this->assertSame(1, SigningAppointment::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function on_ne_planifie_pas_dans_le_local_d_une_autre_societe(): void
    {
        [, $acteur] = $this->societeCliente();

        $autreOrg = OrganizationAccount::factory()->clientCompany()->create();
        $etranger = OrganizationSite::factory()->create([
            'organization_account_id' => $autreOrg->id,
            'site_code' => 'AUTRUI',
        ]);

        Sanctum::actingAs($acteur, ['*']);

        $this->postJson('/api/client/company/signing-appointments', [
            'scheduled_at' => now()->addDays(3)->toIso8601String(),
            'organization_site_id' => $etranger->id,
        ])->assertStatus(422);

        $this->assertSame(0, SigningAppointment::count());
    }

    #[Test]
    public function un_particulier_sans_organisation_n_atteint_pas_l_api(): void
    {
        Sanctum::actingAs(User::factory()->create(['current_organization_id' => null]), ['*']);

        $this->getJson('/api/client/company/sites')->assertForbidden();
    }
}
