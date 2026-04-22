<?php

namespace Tests\Unit;

use App\Models\OrganizationAccount;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Finance\FinanceDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class FinanceDocumentServiceBreakdownTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_amount_breakdown_applies_zone_surcharge_and_entreprise_discount(): void
    {
        $context = $this->createCoverageContext([
            'country_iso' => 'XB',
            'country_iso3' => 'XBE',
            'country_name' => 'Pays XB',
            'zone' => ['travel_surcharge' => 10],
            'service' => ['base_price' => 100, 'default_duration_minutes' => 120],
        ]);

        $organization = OrganizationAccount::factory()->create([
            'metadata' => [
                'finance' => [
                    'negotiated_discount_rate' => 10,
                    'default_employee_hourly_cost' => 18,
                ],
            ],
        ]);

        $employee = User::factory()->employe()->create();
        $rdv = RendezVous::factory()->confirme()->create([
            'organization_account_id' => $organization->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'devis_estime' => 100,
            'duree' => 120,
        ]);

        $breakdown = app(FinanceDocumentService::class)->amountBreakdownFor($rdv);

        $this->assertSame(100.0, $breakdown['base_price']);
        $this->assertSame(10.0, $breakdown['travel_surcharge']);
        $this->assertSame(10.0, $breakdown['discount_rate']);
        $this->assertSame(11.0, $breakdown['discount_amount']);
        $this->assertSame(99.0, $breakdown['subtotal']);
        $this->assertSame(36.0, $breakdown['estimated_internal_cost']);
        $this->assertSame(63.0, $breakdown['estimated_margin_amount']);
    }


    public function test_quote_snapshot_prefers_structured_labels_for_service_and_location(): void
    {
        $context = $this->createCoverageContext([
            'country_iso' => 'XC',
            'country_iso3' => 'XCC',
            'country_name' => 'Pays XC',
            'service' => [
                'name' => 'Grand nettoyage premium',
                'code' => 'grand-premium',
                'slug' => 'grand-premium',
                'service_type' => 'legacy_service',
            ],
        ]);

        $rdv = RendezVous::factory()->confirme()->create([
            'service_catalog_id' => $context['service']->id,
            'service_zone_id' => $context['zone']->id,
            'postal_code_id' => $context['postalCode']->id,
            'adresse' => 'Avenue Finance 22',
            'ville' => 'Bruxelles',
            'code_postal' => null,
        ]);

        $quote = app(FinanceDocumentService::class)->syncQuoteForRendezVous($rdv->fresh(['serviceCatalog', 'serviceZone', 'postalCode']));
        $snapshot = $quote->snapshot;

        $this->assertSame('Grand Nettoyage Premium', $snapshot['service_name']);
        $this->assertSame('grand-premium', $snapshot['service_identifier']);
        $this->assertSame('1000', $snapshot['postal_code']);
        $this->assertSame('Avenue Finance 22, 1000, Bruxelles', $snapshot['location_display']);
    }

}
