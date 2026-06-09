<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\BookingCheckout;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class BookingCheckoutCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create(['stripe_id' => null]);

        if (! Route::has('dashboard.client')) {
            Route::get('/test-stub/dashboard-client', fn () => 'ok')->name('dashboard.client');
        }
    }

    public function test_mount_without_booking_renders_with_publishable_key(): void
    {
        config(['services.stripe.key' => 'pk_test_batch16']);

        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class)
            ->assertOk()
            ->assertSet('bookingId', null)
            ->assertSet('error', '')
            ->assertSet('processing', false)
            ->assertSet('stripePublishableKey', 'pk_test_batch16')
            ->assertViewHas('booking', null);
    }

    public function test_render_with_owned_booking_exposes_it_to_view(): void
    {
        $booking = Booking::factory()->create(['client_id' => $this->client->id]);

        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class, ['bookingId' => $booking->id])
            ->assertOk()
            ->assertSet('bookingId', $booking->id)
            ->assertViewHas('booking', fn ($b) => $b !== null && (int) $b->id === (int) $booking->id);
    }

    public function test_render_with_foreign_booking_returns_null(): void
    {
        $booking = Booking::factory()->create();

        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class, ['bookingId' => $booking->id])
            ->assertOk()
            ->assertViewHas('booking', null);
    }

    public function test_start_payment_with_missing_booking_sets_guard_error(): void
    {
        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class, ['bookingId' => 99999999])
            ->call('startPayment')
            ->assertSet('error', 'Booking introuvable ou non autorisé.')
            ->assertSet('clientSecret', null);
    }

    public function test_start_payment_with_foreign_booking_sets_guard_error(): void
    {
        $booking = Booking::factory()->create();

        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class, ['bookingId' => $booking->id])
            ->call('startPayment')
            ->assertSet('error', 'Booking introuvable ou non autorisé.')
            ->assertSet('clientSecret', null);
    }

    public function test_confirm_authorization_with_missing_booking_sets_guard_error(): void
    {
        Livewire::actingAs($this->client)
            ->test(BookingCheckout::class)
            ->call('confirmAuthorization', 'pm_card_visa')
            ->assertSet('error', 'Booking introuvable.')
            ->assertSet('processing', false);
    }

    public function test_confirm_authorization_when_provider_cannot_receive_payments_sets_error(): void
    {
        $booking = Booking::factory()->create(['client_id' => $this->client->id]);

        $component = Livewire::actingAs($this->client)
            ->test(BookingCheckout::class, ['bookingId' => $booking->id])
            ->call('confirmAuthorization', 'pm_card_visa')
            ->assertSet('processing', false);

        $error = $component->get('error');
        $this->assertStringStartsWith('Erreur paiement : ', $error);
    }
}
