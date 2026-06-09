<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\ClientTipBooking;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage for the ClientTipBooking Livewire component.
 *
 * Exercises mount, preset/custom selection, render (both the no-booking and
 * the resolved-booking branches incl. suggestions + existingTip), and the
 * submit() action across its no-amount, ValidationException and happy paths.
 */
class ClientTipBookingCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The component and its blade view reference route('dashboard.client'),
        // which is not registered as a global alias in the test router. Provide
        // a stub so render() and the submit() redirect are reachable.
        Route::get('/__dash_client_stub', fn () => 'ok')->name('dashboard.client');
    }

    private function completedBooking(User $client, User $provider): Booking
    {
        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'termine',
            'devis_estime' => 100.00,
        ]);
    }

    #[Test]
    public function mount_stores_the_booking_id(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class, ['bookingId' => 4242])
            ->assertOk()
            ->assertSet('bookingId', 4242);
    }

    #[Test]
    public function render_without_booking_id_returns_empty_context(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class)
            ->assertOk()
            ->assertViewHas('booking', null)
            ->assertViewHas('suggestions', [])
            ->assertViewHas('existingTip', null);
    }

    #[Test]
    public function render_with_booking_exposes_suggestions_and_existing_tip(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($client, $provider);

        $tip = BookingTip::query()->create([
            'code' => BookingTip::generateCode(),
            'booking_id' => $booking->id,
            'client_user_id' => $client->id,
            'provider_user_id' => $provider->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_PENDING,
        ]);

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class, ['bookingId' => $booking->id])
            ->assertOk()
            ->assertViewHas('booking', fn ($b) => $b !== null && (int) $b->id === (int) $booking->id)
            ->assertViewHas('suggestions', fn ($s) => is_array($s) && count($s) === 3)
            ->assertViewHas('existingTip', fn ($e) => $e !== null && (int) $e->id === (int) $tip->id);
    }

    #[Test]
    public function select_preset_sets_amount_and_clears_custom(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class)
            ->set('customAmount', '99')
            ->call('selectPreset', 1500, '15%', 15)
            ->assertSet('selectedAmountCents', 1500)
            ->assertSet('selectedPresetLabel', '15%')
            ->assertSet('selectedPresetPercent', 15)
            ->assertSet('customAmount', '');
    }

    #[Test]
    public function use_custom_parses_comma_decimal_amount(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class)
            ->set('customAmount', '12,50')
            ->call('useCustom')
            ->assertSet('selectedAmountCents', 1250)
            ->assertSet('selectedPresetLabel', 'custom')
            ->assertSet('selectedPresetPercent', null);
    }

    #[Test]
    public function use_custom_ignores_non_positive_amount(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class)
            ->set('customAmount', '0')
            ->call('useCustom')
            ->assertSet('selectedAmountCents', null)
            ->assertSet('selectedPresetLabel', null);
    }

    #[Test]
    public function submit_without_amount_dispatches_error_toast(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($client, $provider);

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class, ['bookingId' => $booking->id])
            ->call('submit')
            ->assertDispatched('toast');

        $this->assertSame(0, BookingTip::count());
    }

    #[Test]
    public function submit_on_non_eligible_booking_dispatches_validation_toast(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ]);

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class, ['bookingId' => $booking->id])
            ->call('selectPreset', 1500, '15%', 15)
            ->call('submit')
            ->assertDispatched('toast');

        $this->assertSame(0, BookingTip::count());
    }

    #[Test]
    public function submit_happy_path_creates_charged_tip_and_redirects(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->completedBooking($client, $provider);

        Livewire::actingAs($client)
            ->test(ClientTipBooking::class, ['bookingId' => $booking->id])
            ->call('selectPreset', 1500, '15%', 15)
            ->set('message', 'Merci beaucoup !')
            ->call('submit')
            ->assertDispatched('toast')
            ->assertRedirect(route('dashboard.client'))
            ->assertSet('selectedAmountCents', null)
            ->assertSet('message', '');

        $tip = BookingTip::query()->first();
        $this->assertNotNull($tip);
        $this->assertSame(BookingTip::STATUS_CHARGED, $tip->status);
        $this->assertSame(1500, $tip->amount_cents);
        $this->assertSame('Merci beaucoup !', $tip->message);
        $this->assertSame('pi_dev_'.$tip->id, $tip->stripe_payment_intent_id);
    }
}
