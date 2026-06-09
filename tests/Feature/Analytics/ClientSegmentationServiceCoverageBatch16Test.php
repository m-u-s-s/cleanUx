<?php

namespace Tests\Feature\Analytics;

use App\Enums\CustomerType;
use App\Models\Booking;
use App\Models\CustomerClaim;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Analytics\ClientSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientSegmentationServiceCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    private function service(): ClientSegmentationService
    {
        return app(ClientSegmentationService::class);
    }

    public function test_brand_new_client_with_no_history_is_flagged_nouveau(): void
    {
        $client = User::factory()->client()->create();

        $result = $this->service()->segment($client);

        $this->assertSame($client->id, $result['client_id']);
        $this->assertSame($client->name, $result['name']);
        $this->assertSame($client->email, $result['email']);
        $this->assertContains('Nouveau client', $result['labels']);
        $this->assertSame(0, $result['total_bookings']);
        $this->assertSame(0, $result['completed_bookings']);
        $this->assertSame(0.0, $result['total_revenue']);
        $this->assertSame(0, $result['claims_count']);
        $this->assertSame(0, $result['risk_score']);
        $this->assertSame(0, $result['loyalty_score']);
    }

    public function test_loyal_high_value_client_with_claims_earns_multiple_labels(): void
    {
        $client = User::factory()->client()->create();

        for ($i = 0; $i < 6; $i++) {
            Booking::factory()->create([
                'client_id' => $client->id,
                'status' => $i < 4 ? 'termine' : 'confirme',
                'devis_estime' => 200,
            ]);
        }

        CustomerClaim::create(['client_id' => $client->id, 'status' => 'open']);
        CustomerClaim::create(['client_id' => $client->id, 'status' => 'open']);

        $result = $this->service()->segment($client);

        $this->assertSame(6, $result['total_bookings']);
        $this->assertSame(4, $result['completed_bookings']);
        $this->assertSame(1200.0, $result['total_revenue']);
        $this->assertSame(2, $result['claims_count']);

        $this->assertContains('Client fidèle', $result['labels']);
        $this->assertContains('Forte valeur', $result['labels']);
        $this->assertContains('Client à risque', $result['labels']);
        $this->assertNotContains('Nouveau client', $result['labels']);

        // risk = round(2 / 6 * 100) = 33 ; loyalty = 6*10 + 1200/100 = 72
        $this->assertSame(33, $result['risk_score']);
        $this->assertSame(72, $result['loyalty_score']);
    }

    public function test_client_with_only_old_bookings_is_flagged_inactif(): void
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'termine',
            'devis_estime' => 150,
            'date' => now()->subMonths(6)->format('Y-m-d'),
        ]);

        $result = $this->service()->segment($client);

        $this->assertContains('Inactif', $result['labels']);
        $this->assertNotContains('Nouveau client', $result['labels']);
        $this->assertSame(1, $result['total_bookings']);
        $this->assertSame(0, $result['risk_score']);
    }

    public function test_premium_client_is_labelled_premium(): void
    {
        $client = User::factory()->premiumClient()->create();

        $result = $this->service()->segment($client);

        $this->assertTrue($client->isPremium());
        $this->assertContains('Premium', $result['labels']);
    }

    public function test_company_client_with_several_sites_is_entreprise_and_multi_sites(): void
    {
        $org = OrganizationAccount::factory()->create();

        $client = User::factory()->client()->create([
            'organization_account_id' => $org->id,
        ]);

        CustomerProfile::create([
            'user_id' => $client->id,
            'customer_type' => CustomerType::COMPANY->value,
        ]);

        OrganizationSite::factory()->count(2)->create([
            'organization_account_id' => $org->id,
        ]);

        $result = $this->service()->segment($client->fresh());

        $this->assertContains('Entreprise', $result['labels']);
        $this->assertContains('Multi-sites', $result['labels']);
    }

    public function test_scores_are_capped_at_one_hundred(): void
    {
        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'status' => 'termine',
            'devis_estime' => 50000,
        ]);

        for ($i = 0; $i < 5; $i++) {
            CustomerClaim::create(['client_id' => $client->id, 'status' => 'open']);
        }

        $result = $this->service()->segment($client);

        $this->assertSame(1, $result['total_bookings']);
        $this->assertSame(5, $result['claims_count']);
        $this->assertSame(50000.0, $result['total_revenue']);
        $this->assertContains('Forte valeur', $result['labels']);
        $this->assertContains('Client à risque', $result['labels']);

        // risk = min(100, round(5/1*100)) ; loyalty = min(100, 10 + 50000/100)
        $this->assertSame(100, $result['risk_score']);
        $this->assertSame(100, $result['loyalty_score']);
    }
}
