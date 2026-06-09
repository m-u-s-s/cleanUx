<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\SavedPaymentMethods;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SavedPaymentMethodsCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create(['stripe_id' => null]);
    }

    public function test_render_without_stripe_customer_shows_empty_methods(): void
    {
        Livewire::actingAs($this->client)
            ->test(SavedPaymentMethods::class)
            ->assertOk()
            ->assertViewHas('methods', [])
            ->assertViewHas('defaultId', null)
            ->assertViewHas('stripeKey')
            ->assertSet('error', '');
    }

    public function test_set_default_without_stripe_customer_dispatches_error_toast(): void
    {
        Livewire::actingAs($this->client)
            ->test(SavedPaymentMethods::class)
            ->call('setDefault', 'pm_nonexistent')
            ->assertDispatched('toast');
    }

    public function test_remove_without_stripe_customer_is_a_noop(): void
    {
        Livewire::actingAs($this->client)
            ->test(SavedPaymentMethods::class)
            ->call('remove', 'pm_nonexistent')
            ->assertOk();
    }
}
