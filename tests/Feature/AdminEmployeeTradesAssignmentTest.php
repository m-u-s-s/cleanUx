<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminEmployeeTradesAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    protected function makeEmployee(): User
    {
        return User::factory()->create([
            'role'      => User::ROLE_EMPLOYE,
            'is_active' => true,
        ]);
    }

    protected function makeTrade(string $slug): Trade
    {
        return Trade::create([
            'slug' => $slug, 'code' => strtoupper($slug),
            'name' => ucfirst($slug),
            'is_active' => true, 'sort_order' => 10,
        ]);
    }

    public function test_admin_can_open_trades_panel_for_employee_with_no_trades(): void
    {
        $employee = $this->makeEmployee();
        $this->makeTrade('peinture');
        $this->makeTrade('serrurerie');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionUtilisateurs::class)
            ->call('openEmployeeTrades', $employee->id)
            ->assertSet('editingTradesUserId', $employee->id)
            ->assertSet('employeeTradesSelection', [])
            ->assertSet('employeeTradesPrimary', null);
    }

    public function test_admin_can_assign_two_trades_and_set_one_as_primary(): void
    {
        $employee = $this->makeEmployee();
        $peinture = $this->makeTrade('peinture');
        $serrurerie = $this->makeTrade('serrurerie');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionUtilisateurs::class)
            ->call('openEmployeeTrades', $employee->id)
            ->call('toggleEmployeeTrade', $peinture->id)
            ->call('toggleEmployeeTrade', $serrurerie->id)
            ->set("employeeTradesSelection.$peinture->id.proficiency", 'expert')
            ->call('setEmployeeTradePrimary', $serrurerie->id)
            ->call('saveEmployeeTrades');

        $employee->refresh()->load('trades');
        $this->assertCount(2, $employee->trades);

        $this->assertDatabaseHas('trade_user', [
            'user_id'  => $employee->id,
            'trade_id' => $peinture->id,
            'is_primary' => false,
            'proficiency' => 'expert',
        ]);
        $this->assertDatabaseHas('trade_user', [
            'user_id'  => $employee->id,
            'trade_id' => $serrurerie->id,
            'is_primary' => true,
        ]);
    }

    public function test_unchecking_primary_trade_clears_primary(): void
    {
        $employee = $this->makeEmployee();
        $peinture = $this->makeTrade('peinture');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionUtilisateurs::class)
            ->call('openEmployeeTrades', $employee->id)
            ->call('toggleEmployeeTrade', $peinture->id)
            ->call('setEmployeeTradePrimary', $peinture->id)
            ->call('toggleEmployeeTrade', $peinture->id) // uncheck
            ->assertSet('employeeTradesPrimary', null);
    }

    public function test_save_syncs_replaces_previous_trades(): void
    {
        $employee = $this->makeEmployee();
        $peinture = $this->makeTrade('peinture');
        $serrurerie = $this->makeTrade('serrurerie');
        $jardinage = $this->makeTrade('jardinage');

        // L'employé a déjà peinture + serrurerie
        $employee->trades()->sync([$peinture->id => [], $serrurerie->id => []]);

        $this->actingAs($this->createAdmin());

        // On ouvre, on retire serrurerie, on ajoute jardinage
        Livewire::test(GestionUtilisateurs::class)
            ->call('openEmployeeTrades', $employee->id)
            ->call('toggleEmployeeTrade', $serrurerie->id) // uncheck
            ->call('toggleEmployeeTrade', $jardinage->id)  // check
            ->call('saveEmployeeTrades');

        $tradeIds = $employee->fresh()->trades->pluck('id')->sort()->values()->all();
        $this->assertSame(
            collect([$peinture->id, $jardinage->id])->sort()->values()->all(),
            $tradeIds
        );
    }

    public function test_invalid_proficiency_is_rejected(): void
    {
        $employee = $this->makeEmployee();
        $trade = $this->makeTrade('peinture');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionUtilisateurs::class)
            ->call('openEmployeeTrades', $employee->id)
            ->call('toggleEmployeeTrade', $trade->id)
            ->set("employeeTradesSelection.$trade->id.proficiency", 'master_yoda')
            ->call('saveEmployeeTrades')
            ->assertHasErrors(["employeeTradesSelection.$trade->id.proficiency"]);

        $this->assertDatabaseMissing('trade_user', [
            'user_id'  => $employee->id,
            'trade_id' => $trade->id,
        ]);
    }

    public function test_non_admin_cannot_assign_trades(): void
    {
        $employee = $this->makeEmployee();
        $trade = $this->makeTrade('peinture');

        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT, 'is_active' => true,
        ]);
        $this->actingAs($client);

        try {
            Livewire::test(GestionUtilisateurs::class)
                ->call('openEmployeeTrades', $employee->id);
        } catch (\Throwable $e) {
            $this->assertTrue(
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || str_contains(strtolower($e->getMessage()), 'unauthorized')
                || str_contains(strtolower($e->getMessage()), 'forbidden'),
                'Non-admin doit être bloqué par autorisation.'
            );
        }

        $this->assertDatabaseMissing('trade_user', [
            'user_id'  => $employee->id,
            'trade_id' => $trade->id,
        ]);
    }
}
