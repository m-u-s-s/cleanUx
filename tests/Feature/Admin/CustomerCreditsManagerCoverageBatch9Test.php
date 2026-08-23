<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\CustomerCreditsManager;
use App\Models\Booking;
use App\Models\CustomerCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Coverage for the CustomerCreditsManager admin Livewire component: render with the clientFacing client list + booking filter + credit search, createCredit happy path (with and without a linked rendez_vous), validation guard, and cancelCredit on both active and non-active credits. */
class CustomerCreditsManagerCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
        ]);
    }

    protected function makeClient(string $name = 'Alice Client'): User
    {
        return User::factory()->client()->create([
            'name' => $name,
            'is_active' => true,
        ]);
    }

    public function test_render_lists_clients_and_credits(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'commercial_gesture',
            'amount' => 30.0,
            'remaining_amount' => 30.0,
            'status' => 'active',
            'reason' => 'seed',
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->assertOk()
            ->assertSet('type', 'commercial_gesture')
            ->assertViewHas('clients')
            ->assertViewHas('credits');
    }

    public function test_create_credit_persists_resets_and_dispatches_toast(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->set('client_id', $client->id)
            ->set('type', 'referral')
            ->set('amount', 42.0)
            ->set('reason', 'Geste commercial')
            ->set('notes', 'Note interne')
            ->call('createCredit')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('amount', 0)
            ->assertSet('reason', '')
            ->assertSet('type', 'commercial_gesture');

        $this->assertDatabaseHas('customer_credits', [
            'client_id' => $client->id,
            'type' => 'referral',
            'amount' => 42.0,
            'remaining_amount' => 42.0,
            'status' => 'active',
            'reason' => 'Geste commercial',
        ]);
    }

    public function test_create_credit_with_linked_rendez_vous(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        $rendezVousId = DB::table('bookings')->insertGetId([
            'client_id' => $client->id,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->set('client_id', $client->id)
            ->set('rendez_vous_id', $rendezVousId)
            ->set('amount', 15.0)
            ->set('reason', 'Compensation')
            ->call('createCredit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('customer_credits', [
            'client_id' => $client->id,
            'rendez_vous_id' => $rendezVousId,
            'amount' => 15.0,
        ]);
    }

    public function test_create_credit_validates_amount(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->set('client_id', $client->id)
            ->set('amount', 0)
            ->set('reason', '')
            ->call('createCredit')
            ->assertHasErrors(['amount', 'reason']);

        $this->assertDatabaseCount('customer_credits', 0);
    }

    public function test_render_booking_filter_and_search(): void
    {
        $admin = $this->makeAdmin();
        $alice = $this->makeClient('Alice Searchable');
        $bob = $this->makeClient('Bob Other');

        Booking::factory()->create(['client_id' => $alice->id]);

        CustomerCredit::create([
            'client_id' => $alice->id,
            'type' => 'commercial_gesture',
            'amount' => 10.0,
            'remaining_amount' => 10.0,
            'status' => 'active',
            'reason' => 'alice credit',
        ]);
        CustomerCredit::create([
            'client_id' => $bob->id,
            'type' => 'commercial_gesture',
            'amount' => 20.0,
            'remaining_amount' => 20.0,
            'status' => 'active',
            'reason' => 'bob credit',
        ]);

        $component = Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->set('client_id', $alice->id)
            ->set('search', 'Searchable')
            ->assertOk();

        $this->assertCount(1, $component->viewData('credits'));
    }

    public function test_cancel_active_credit(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        $credit = CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'commercial_gesture',
            'amount' => 50.0,
            'remaining_amount' => 50.0,
            'status' => 'active',
            'reason' => 'to cancel',
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->call('cancelCredit', $credit->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('customer_credits', [
            'id' => $credit->id,
            'status' => 'cancelled',
            'remaining_amount' => 0,
        ]);
    }

    public function test_cancel_non_active_credit_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $client = $this->makeClient();

        $credit = CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'commercial_gesture',
            'amount' => 50.0,
            'remaining_amount' => 50.0,
            'status' => 'cancelled',
            'reason' => 'already cancelled',
        ]);

        Livewire::actingAs($admin)
            ->test(CustomerCreditsManager::class)
            ->call('cancelCredit', $credit->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('customer_credits', [
            'id' => $credit->id,
            'status' => 'cancelled',
        ]);
    }
}
