<?php

namespace Tests\Feature\Admin\TripTracking;

use App\Livewire\Admin\TripTracking\TripTrackingCenter;
use App\Models\TripTrackingSession;
use App\Models\User;
use Database\Factories\TripTrackingPointFactory;
use Database\Factories\TripTrackingSessionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Coverage for the TripTrackingCenter admin Livewire component: render with live + history sessions and stats, setTab/openSession/closeDetail interactions, the history status + search filters, the selected-session detail with replay points, and the cancelSession action that delegates to TripTrackingService. */
class TripTrackingCenterCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create(['is_active' => true]);
    }

    public function test_render_shows_live_and_history_sessions_with_stats(): void
    {
        $admin = $this->makeAdmin();

        $enroute = TripTrackingSessionFactory::new()->create();
        $arrived = TripTrackingSessionFactory::new()->arrived()->create();
        $inMission = TripTrackingSessionFactory::new()->inMission()->create();
        TripTrackingSessionFactory::new()->ended()->create();

        Livewire::actingAs($admin)
            ->test(TripTrackingCenter::class)
            ->assertOk()
            ->assertSet('tab', 'live')
            ->assertViewHas('liveSessions', fn ($s) => $s->count() === 3)
            ->assertViewHas('stats', function (array $stats) {
                return $stats['active_now'] === 3
                    && $stats['enroute'] === 1
                    && $stats['arrived'] === 1
                    && $stats['in_mission'] === 1
                    && $stats['total_history'] === 1;
            });

        $this->assertNotNull($enroute->id);
        $this->assertNotNull($arrived->id);
        $this->assertNotNull($inMission->id);
    }

    public function test_set_tab_switches_active_tab(): void
    {
        Livewire::actingAs($this->makeAdmin())
            ->test(TripTrackingCenter::class)
            ->call('setTab', 'history')
            ->assertSet('tab', 'history');
    }

    public function test_history_filters_by_status_and_search(): void
    {
        $admin = $this->makeAdmin();

        $provider = User::factory()->employe()->create(['name' => 'Zorro Provider']);
        $matching = TripTrackingSessionFactory::new()->ended()->create([
            'provider_user_id' => $provider->id,
            'code' => 'trip_findme_code',
        ]);
        TripTrackingSessionFactory::new()->create(); // active, must not appear in history

        // Cancelled session for the status filter branch.
        TripTrackingSessionFactory::new()->create([
            'status' => TripTrackingSession::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(TripTrackingCenter::class)
            ->set('statusFilter', TripTrackingSession::STATUS_ENDED)
            ->set('search', 'Zorro')
            ->assertViewHas('historySessions', function ($paginator) use ($matching) {
                return $paginator->total() === 1
                    && $paginator->first()->id === $matching->id;
            });
    }

    public function test_open_and_close_session_detail_with_points(): void
    {
        $admin = $this->makeAdmin();

        $session = TripTrackingSessionFactory::new()->inMission()->create();
        TripTrackingPointFactory::new()->count(3)->create([
            'session_id' => $session->id,
        ]);

        $component = Livewire::actingAs($admin)
            ->test(TripTrackingCenter::class)
            ->call('openSession', $session->id)
            ->assertSet('selectedSessionId', $session->id)
            ->assertViewHas('selectedSession', fn ($s) => $s !== null && $s->id === $session->id)
            ->assertViewHas('selectedPoints', fn ($pts) => $pts->count() === 3);

        $component->call('closeDetail')
            ->assertSet('selectedSessionId', null)
            ->assertViewHas('selectedSession', null);
    }

    public function test_cancel_session_marks_cancelled_and_dispatches_toast(): void
    {
        $admin = $this->makeAdmin();

        $session = TripTrackingSessionFactory::new()->create();

        Livewire::actingAs($admin)
            ->test(TripTrackingCenter::class)
            ->call('cancelSession', $session->id)
            ->assertDispatched('toast');

        $this->assertSame(
            TripTrackingSession::STATUS_CANCELLED,
            $session->fresh()->status,
        );
    }
}
