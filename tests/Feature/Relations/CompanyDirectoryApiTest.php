<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * SP3 Task 7 — GET /api/client/companies liste les SOCIÉTÉS prestataires
 * éligibles pour un métier (via service_catalog_id) + zone (service_zone_id),
 * triées par note décroissante, avec un providers_count par société.
 *
 * Réutilise EligibleCompaniesResolver (SP3 Task 3) : une société sans worker
 * couvrant le métier demandé est ABSENTE du listing. Auth Sanctum requise.
 */
class CompanyDirectoryApiTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /**
     * Crée un worker société par ailleurs éligible (User actif rattaché à la zone,
     * ProviderProfile company_worker actif+vérifié rattaché à l'org, disponibilité
     * couvrant le créneau) et le tagge du métier donné.
     */
    private function companyWorker(int $zoneId, int $orgId, int $tradeId, string $date): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $orgId,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        Disponibilite::create([
            'user_id' => $user->id,
            'date' => $date,
            'heure_debut' => '08:00:00',
            'heure_fin' => '18:00:00',
        ]);

        $user->trades()->sync([$tradeId]);

        return $user;
    }

    public function test_lists_eligible_companies_sorted_by_rating_with_providers_count(): void
    {
        $tradeWanted = Trade::factory()->create();
        $tradeOther = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $tradeWanted->id],
        ]);
        $zoneId = $context['zone']->id;
        $catalog = $context['service'];
        $date = now()->addDays(3)->toDateString();

        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.8,
            'rating_count' => 12,
            'name' => 'Alpha Clean',
        ]);
        $orgB = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.2,
            'rating_count' => 5,
            'name' => 'Beta Services',
        ]);
        // orgC : worker taggé d'un AUTRE métier → ABSENTE malgré sa note supérieure.
        $orgC = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 5.0,
            'name' => 'Gamma Excluded',
        ]);

        // orgA : 2 workers du bon métier → providers_count = 2.
        $this->companyWorker($zoneId, $orgA->id, $tradeWanted->id, $date);
        $this->companyWorker($zoneId, $orgA->id, $tradeWanted->id, $date);
        // orgB : 1 worker du bon métier.
        $this->companyWorker($zoneId, $orgB->id, $tradeWanted->id, $date);
        // orgC : worker d'un autre métier.
        $this->companyWorker($zoneId, $orgC->id, $tradeOther->id, $date);

        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]));

        $response = $this->getJson(
            "/api/client/companies?service_catalog_id={$catalog->id}&service_zone_id={$zoneId}"
        )->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'rating_avg', 'rating_count', 'providers_count'],
                ],
            ]);

        $data = $response->json('data');
        $ids = collect($data)->pluck('id')->all();

        // orgA (4.8) avant orgB (4.2) → tri par note ; orgC (5.0) absente → filtre métier.
        $this->assertSame([$orgA->id, $orgB->id], $ids);
        $this->assertNotContains($orgC->id, $ids);

        // providers_count reflète le nombre de workers company_worker actifs+vérifiés.
        $this->assertSame(2, $data[0]['providers_count']);
        $this->assertSame(1, $data[1]['providers_count']);
        $this->assertSame('Alpha Clean', $data[0]['name']);
    }

    public function test_requires_authentication(): void
    {
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;

        $this->getJson("/api/client/companies?service_zone_id={$zoneId}")
            ->assertUnauthorized();
    }

    public function test_requires_service_zone_id(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]));

        $this->getJson('/api/client/companies')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service_zone_id']);
    }
}
