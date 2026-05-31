<?php

namespace Tests\Feature\Relations;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_independent_provider_has_employe_role_and_trades(): void
    {
        $trade = Trade::factory()->create();

        $user = app(CreateNewUser::class)->create([
            'name' => 'Indep Test',
            'email' => 'indep@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'provider_independent',
            'trade_ids' => [$trade->id],
        ]);

        $this->assertSame('employe', $user->fresh()->role);
        $this->assertTrue($user->isProviderIndependent());
        $this->assertTrue($user->trades()->where('trades.id', $trade->id)->exists());
    }
}
