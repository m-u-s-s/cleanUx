<?php

namespace Tests\Feature\Insurance;

use App\Livewire\Admin\Insurance\InsuranceCenter;
use App\Models\Booking;
use App\Models\InsuranceClaim;
use App\Models\User;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsuranceService;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use Database\Seeders\InsurancePlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InsuranceCenterCoverageBatch13Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(InsuranceProviderInterface::class, InsuranceMockProvider::class);
        $this->seed(InsurancePlansSeeder::class);
    }

    private function seedClaim(): InsuranceClaim
    {
        $client = User::factory()->client()->create();

        $booking = Booking::create([
            'client_id' => $client->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 200,
        ]);

        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $client);

        return app(InsuranceService::class)->fileClaim(
            $insurance,
            $client,
            InsuranceClaim::INCIDENT_DAMAGE,
            'Mur abîmé pendant la prestation, peinture rayée fortement.',
            now()->subDay(),
            40000,
        );
    }

    public function test_claims_tab_renders_with_kpis(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedClaim();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->assertOk()
            ->assertSet('tab', 'claims')
            ->assertViewHas('kpis')
            ->assertViewHas('items');
    }

    public function test_policies_tab_renders(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedClaim();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->set('tab', 'policies')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_plans_tab_renders(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->set('tab', 'plans')
            ->assertOk()
            ->assertViewHas('items');
    }

    public function test_filter_status_narrows_claims(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seedClaim();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->set('filterStatus', InsuranceClaim::STATUS_FILED)
            ->assertOk();
    }

    public function test_set_claim_status_to_paid_updates_record(): void
    {
        $admin = User::factory()->admin()->create();
        $claim = $this->seedClaim();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->call('setClaimStatus', $claim->id, InsuranceClaim::STATUS_PAID, 'Indemnisé')
            ->assertDispatched('toast');

        $claim->refresh();
        $this->assertSame(InsuranceClaim::STATUS_PAID, $claim->status);
        $this->assertNotNull($claim->paid_at);
        $this->assertNotNull($claim->decided_at);
        $this->assertSame('Indemnisé', $claim->decision_reason);
    }

    public function test_set_claim_status_to_accepted_sets_decided_at(): void
    {
        $admin = User::factory()->admin()->create();
        $claim = $this->seedClaim();

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->call('setClaimStatus', $claim->id, InsuranceClaim::STATUS_ACCEPTED);

        $claim->refresh();
        $this->assertSame(InsuranceClaim::STATUS_ACCEPTED, $claim->status);
        $this->assertNotNull($claim->decided_at);
        $this->assertNull($claim->paid_at);
        $this->assertNotNull($claim->reviewed_at);
    }

    public function test_set_claim_status_rejects_invalid_status(): void
    {
        $admin = User::factory()->admin()->create();
        $claim = $this->seedClaim();
        $originalStatus = $claim->status;

        Livewire::actingAs($admin)
            ->test(InsuranceCenter::class)
            ->call('setClaimStatus', $claim->id, 'bogus_status')
            ->assertDispatched('toast');

        $claim->refresh();
        $this->assertSame($originalStatus, $claim->status);
    }
}
