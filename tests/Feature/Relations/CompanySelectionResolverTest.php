<?php

namespace Tests\Feature\Relations;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Disponibilite;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Booking\ProviderSelectionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** SP3 Task 6 — Le client peut CHOISIR une SOCIÉTÉ prestataire pour sa réservation. */
class CompanySelectionResolverTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

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

    public function test_choosing_an_eligible_company_requires_premium(): void
    {
        $tradeWanted = Trade::factory()->create();
        $tradeOther = Trade::factory()->create();

        $context = $this->createCoverageContext([
            'service' => ['trade_id' => $tradeWanted->id],
        ]);
        $zoneId = $context['zone']->id;
        $catalog = $context['service'];
        $date = now()->addDays(3)->toDateString();

        // orgA : ÉLIGIBLE (worker du bon métier). orgZ : NON éligible (autre métier).
        $orgA = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.8,
        ]);
        $orgZ = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 5.0,
        ]);

        $this->companyWorker($zoneId, $orgA->id, $tradeWanted->id, $date);
        $this->companyWorker($zoneId, $orgZ->id, $tradeOther->id, $date);

        // Contexte = booking transitoire (zone + service) pour la validation d'éligibilité.
        $bookingContext = Booking::factory()->make([
            'service_catalog_id' => $catalog->id,
            'service_zone_id' => $zoneId,
            'date' => $date,
        ]);

        $resolver = app(ProviderSelectionResolver::class);

        // 1) Client NON premium choisit orgA (éligible) → refus (gate premium).
        $freeClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        CustomerProfile::create([
            'user_id' => $freeClient->id,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        try {
            $resolver->resolve(
                $freeClient,
                ['assigned_provider_organization_id' => $orgA->id],
                $bookingContext,
            );
            $this->fail('Un client non-premium ne doit pas pouvoir imposer une société.');
        } catch (AuthorizationException $e) {
            $this->assertTrue(true);
        }

        // 2) Client PREMIUM choisit orgA (éligible) → OK, org persistée.
        $premiumClient = User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
        CustomerProfile::create([
            'user_id' => $premiumClient->id,
            'plan_type' => 'premium',
            'plan_status' => 'active',
            'premium_started_at' => now()->subMonths(3),
            'premium_renewal_at' => now()->addMonths(9),
        ]);

        $out = $resolver->resolve(
            $premiumClient->fresh(),
            ['assigned_provider_organization_id' => $orgA->id],
            $bookingContext,
        );

        $this->assertSame($orgA->id, $out['assigned_provider_organization_id']);
        // Org et worker précis mutuellement exclusifs : l'org prime.
        $this->assertNull($out['preferred_provider_user_id']);

        // 3) Client PREMIUM choisit orgZ (NON éligible) → refus (validation éligibilité).
        try {
            $resolver->resolve(
                $premiumClient->fresh(),
                ['assigned_provider_organization_id' => $orgZ->id],
                $bookingContext,
            );
            $this->fail('Une société non éligible ne doit pas être acceptée.');
        } catch (AuthorizationException $e) {
            $this->assertTrue(true);
        }
    }
}
