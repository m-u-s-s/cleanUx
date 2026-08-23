<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\EligibleCompaniesResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP3 Task 3 — EligibleCompaniesResolver retourne les SOCIÉTÉS prestataires réservables pour une réservation (≥1 worker éligible couvrant métier+zone), triées par note décroissante. */
class EligibleCompaniesResolverTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    /** Crée un worker société PAR AILLEURS éligible (User actif rattaché à la zone, ProviderProfile company_worker actif+vérifié rattaché à l'org, disponibilité couvrant le créneau) et le tagge du métier donné. */
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

    public function test_returns_provider_companies_with_an_eligible_worker_sorted_by_rating(): void
    {
        $tradeWanted = Trade::factory()->create();
        $tradeOther = Trade::factory()->create();

        // service_catalog lié au métier voulu ; zone via les fixtures partagées.
        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $tradeWanted->id],
        ]);
        $zoneId = $context['zone']->id;
        $catalog = $context['service'];
        $date = now()->addDays(3)->toDateString();

        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.8,
        ]);
        $orgB = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.2,
        ]);
        $orgC = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 5.0,
        ]);

        // orgA + orgB : worker taggé du BON métier.
        $this->companyWorker($zoneId, $orgA->id, $tradeWanted->id, $date);
        $this->companyWorker($zoneId, $orgB->id, $tradeWanted->id, $date);
        // orgC : worker taggé d'un AUTRE métier → pas éligible pour CE booking.
        $this->companyWorker($zoneId, $orgC->id, $tradeOther->id, $date);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);

        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => null,
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
            'heure' => '10:00:00',
            'duree' => 90,
            'duree_estimee' => 90,
            'booking_mode' => 'scheduled',
            'status' => 'en_attente',
        ]);

        $companies = app(EligibleCompaniesResolver::class)->forBooking($booking->fresh());
        $ids = $companies->pluck('id')->all();

        // orgA (4.8) avant orgB (4.2) → tri par note prouvé ; orgC (5.0) absente → filtre métier prouvé.
        $this->assertSame([$orgA->id, $orgB->id], $ids);
        $this->assertNotContains($orgC->id, $ids);
    }

    public function test_without_a_trade_filter_all_companies_with_an_eligible_worker_are_returned(): void
    {
        // Sans métier (forContext tradeId null) le filtre s'ouvre VOLONTAIREMENT :
        // toute société ayant un worker éligible dans la zone est listée (triée par note).
        $context = $this->createCoverageContext();
        $zoneId = $context['zone']->id;
        $date = now()->addDays(3)->toDateString();
        $anyTrade = Trade::factory()->create();

        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value, 'rating_avg' => 4.0,
        ]);
        $orgB = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value, 'rating_avg' => 4.9,
        ]);
        $this->companyWorker($zoneId, $orgA->id, $anyTrade->id, $date);
        $this->companyWorker($zoneId, $orgB->id, $anyTrade->id, $date);

        $ids = app(EligibleCompaniesResolver::class)->forContext($zoneId, null)->pluck('id')->all();

        // Les deux présentes (filtre métier ouvert), triées par note desc (orgB 4.9 avant orgA 4.0).
        $this->assertSame([$orgB->id, $orgA->id], $ids);
    }
}
