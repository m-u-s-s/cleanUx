<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\EmployeeRateClient;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeRateClientCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected User $provider;

    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // The component + blade reference route('dashboard.employe'), which is not
        // defined in the app (the real route is named 'employe.dashboard'). Register
        // it at runtime so the view can render and the component is exercisable.
        Route::get('/dashboard/employe', fn () => 'ok')->name('dashboard.employe');

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->booking = Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'mission_finished_at' => now()->subDay(),
            'devis_estime' => 100,
        ]);
    }

    public function test_mount_stores_booking_id(): void
    {
        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->assertOk()
            ->assertSet('bookingId', $this->booking->id)
            ->assertSet('rating', 5)
            ->assertSet('punctuality', 5)
            ->assertSet('quality', 5)
            ->assertSet('communication', 5);
    }

    public function test_render_resolves_booking_for_owning_provider(): void
    {
        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->assertOk()
            ->assertViewHas('booking', fn ($booking) => $booking !== null && $booking->id === $this->booking->id)
            ->assertViewHas('existing', null);
    }

    public function test_render_hides_booking_for_non_owner(): void
    {
        $stranger = User::factory()->employe()->create([
            'is_active' => true,
            'status' => 'active',
        ]);
        Livewire::actingAs($stranger);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->assertOk()
            ->assertViewHas('booking', null)
            ->assertViewHas('existing', null);
    }

    public function test_render_returns_null_booking_when_no_id(): void
    {
        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class)
            ->assertOk()
            ->assertViewHas('booking', null)
            ->assertViewHas('existing', null);
    }

    public function test_submit_persists_provider_to_client_feedback(): void
    {
        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->set('rating', 4)
            ->set('comment', 'Client agréable et ponctuel.')
            ->set('punctuality', 5)
            ->set('quality', 4)
            ->set('communication', 3)
            ->call('submit')
            ->assertDispatched('toast')
            ->assertRedirect(route('dashboard.employe'));

        $feedback = Feedback::query()
            ->where('booking_id', $this->booking->id)
            ->where('direction', Feedback::DIRECTION_PROVIDER_TO_CLIENT)
            ->first();

        $this->assertNotNull($feedback);
        $this->assertSame(4, (int) $feedback->rating);
        $this->assertSame('Client agréable et ponctuel.', $feedback->comment);
        $this->assertSame(Feedback::STATUS_PENDING, $feedback->status);
    }

    public function test_submit_rejects_out_of_range_rating(): void
    {
        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->set('rating', 9)
            ->call('submit')
            ->assertHasErrors(['rating']);

        $this->assertSame(0, Feedback::query()
            ->where('booking_id', $this->booking->id)
            ->count());
    }

    public function test_submit_on_uncompleted_booking_dispatches_error_toast(): void
    {
        $this->booking->update(['status' => 'confirme', 'mission_finished_at' => null]);

        Livewire::actingAs($this->provider);

        Livewire::test(EmployeeRateClient::class, ['bookingId' => $this->booking->id])
            ->set('rating', 5)
            ->call('submit')
            ->assertDispatched('toast');

        $this->assertSame(0, Feedback::query()
            ->where('booking_id', $this->booking->id)
            ->where('direction', Feedback::DIRECTION_PROVIDER_TO_CLIENT)
            ->count());
    }
}
