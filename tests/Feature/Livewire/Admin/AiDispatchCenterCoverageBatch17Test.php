<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\AiDispatchCenter;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiDispatchCenterCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * Le métier commun aux fixtures.
     *
     * Il est OBLIGATOIRE depuis que le filtre métier n'a plus de repli : une réservation sans
     * métier résolvable ne rend AUCUN candidat, au lieu de les rendre tous.
     */
    private function trade(): \App\Models\Trade
    {
        return \App\Models\Trade::firstOrCreate(
            ['slug' => 'ai-center-trade'],
            ['code' => 'AIC', 'name' => 'Nettoyage', 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function eligibleProviderForZone(int $zoneId): User
    {
        $provider = User::factory()->employe()->create([
            'is_active' => true,
            'primary_service_zone_id' => $zoneId,
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'verification_status' => 'verified',
            'provider_type' => 'independent',
            'is_online' => true,
        ]);

        $provider->trades()->syncWithoutDetaching([$this->trade()->id]);

        return $provider;
    }

    public function test_renders_with_default_status_filter(): void
    {
        Booking::factory()->enAttente()->create();
        Booking::factory()->confirme()->create();

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->assertOk()
            ->assertSet('status', 'en_attente')
            ->assertSet('search', '')
            ->assertViewHas('rendezVous');
    }

    public function test_status_filter_can_be_changed(): void
    {
        Booking::factory()->confirme()->create();

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->set('status', 'confirme')
            ->assertOk()
            ->assertSet('status', 'confirme');
    }

    public function test_search_filter_applies_structured_search(): void
    {
        $booking = Booking::factory()->enAttente()->create();

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->set('search', $booking->booking_reference)
            ->assertOk()
            ->assertSet('search', $booking->booking_reference);
    }

    public function test_preview_populates_ranking_and_id(): void
    {
        $booking = Booking::factory()->enAttente()->create(['trade_id' => $this->trade()->id]);
        $this->eligibleProviderForZone((int) $booking->service_zone_id);

        $component = Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->call('preview', $booking->id)
            ->assertOk()
            ->assertSet('previewRdvId', $booking->id);

        $ranking = $component->get('ranking');
        $this->assertNotEmpty($ranking);
        $this->assertArrayHasKey('employee_id', $ranking[0]);
        $this->assertArrayHasKey('score', $ranking[0]);
        $this->assertArrayHasKey('details', $ranking[0]);
    }

    public function test_close_preview_resets_state(): void
    {
        $booking = Booking::factory()->enAttente()->create();

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->call('preview', $booking->id)
            ->assertSet('previewRdvId', $booking->id)
            ->call('closePreview')
            ->assertSet('previewRdvId', null)
            ->assertSet('ranking', []);
    }

    public function test_assign_without_eligible_employee_dispatches_error(): void
    {
        config(['matching.enabled' => false]);

        $booking = Booking::factory()->enAttente()->create(['employe_id' => null]);

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->call('assign', $booking->id)
            ->assertOk()
            ->assertDispatched('toast');

        $this->assertSame(BookingStatus::EN_ATTENTE, $booking->fresh()->status);
    }

    public function test_assign_with_eligible_employee_confirms_booking(): void
    {
        config(['matching.enabled' => false]);

        $booking = Booking::factory()->enAttente()->create([
            'employe_id' => null,
            'trade_id' => $this->trade()->id,
        ]);
        $provider = $this->eligibleProviderForZone((int) $booking->service_zone_id);

        Livewire::actingAs($this->admin)
            ->test(AiDispatchCenter::class)
            ->call('assign', $booking->id)
            ->assertOk()
            ->assertDispatched('toast');

        $fresh = $booking->fresh();
        $this->assertSame($provider->id, $fresh->employe_id);
        $this->assertSame(BookingStatus::CONFIRME, $fresh->status);
    }
}
