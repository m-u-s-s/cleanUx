<?php

namespace Tests\Feature\Loyalty;

use App\Livewire\Admin\Loyalty\LoyaltyCenter;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Database\Seeders\LoyaltyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoyaltyCenterCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoyaltyTierSeeder::class);
    }

    public function test_renders_with_kpis_and_distribution(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create(['name' => 'Alice Render', 'email' => 'alice.render@example.com']);
        app(LoyaltyService::class)->award($client, LoyaltyTransaction::TYPE_EARN_SIGNUP, 250, null, 'render_seed');

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->assertOk()
            ->assertViewHas('kpis')
            ->assertViewHas('tiers')
            ->assertViewHas('distribution')
            ->assertViewHas('members')
            ->assertSee('Alice Render');
    }

    public function test_search_filters_members(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(LoyaltyService::class);
        $found = User::factory()->client()->create(['name' => 'Findable Person', 'email' => 'findable@example.com']);
        $other = User::factory()->client()->create(['name' => 'Hidden Person', 'email' => 'hidden@example.com']);
        $service->award($found, LoyaltyTransaction::TYPE_EARN_SIGNUP, 100, null, 'k_found');
        $service->award($other, LoyaltyTransaction::TYPE_EARN_SIGNUP, 100, null, 'k_other');

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->set('search', 'Findable')
            ->assertOk()
            ->assertSee('Findable Person')
            ->assertDontSee('Hidden Person');
    }

    public function test_filter_by_tier(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $account = app(LoyaltyService::class)->accountFor($client);

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->set('filterTierId', $account->current_tier_id)
            ->assertOk()
            ->assertViewHas('members');
    }

    public function test_select_user_and_close_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        app(LoyaltyService::class)->award($client, LoyaltyTransaction::TYPE_EARN_SIGNUP, 120, null, 'sel_seed');

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->call('selectUser', $client->id)
            ->assertSet('selectedUserId', $client->id)
            ->assertViewHas('selected')
            ->call('closeDetail')
            ->assertSet('selectedUserId', null);
    }

    public function test_adjust_applies_loyalty_adjustment(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        app(LoyaltyService::class)->accountFor($client);

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->call('selectUser', $client->id)
            ->set('adjustPoints', 300)
            ->set('adjustReason', 'Compensation goodwill client')
            ->call('adjust')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $account = LoyaltyAccount::where('user_id', $client->id)->first();
        $this->assertSame(300, (int) $account->lifetime_points);

        $this->assertDatabaseHas('loyalty_transactions', [
            'loyalty_account_id' => $account->id,
            'direction' => 'credit',
        ]);
    }

    public function test_adjust_validation_rejects_zero_and_short_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        app(LoyaltyService::class)->accountFor($client);

        Livewire::actingAs($admin)
            ->test(LoyaltyCenter::class)
            ->call('selectUser', $client->id)
            ->set('adjustPoints', 0)
            ->set('adjustReason', 'x')
            ->call('adjust')
            ->assertHasErrors(['adjustPoints', 'adjustReason']);
    }
}
