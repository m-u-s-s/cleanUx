<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\AdminHomeDashboard;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** M21 — the 7-day bookings trend must be computed in a single grouped query, not 7. */
class AdminHomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_bookings_trend_is_correct_and_single_query(): void
    {
        Booking::factory()->count(2)->create(['created_at' => now()]);
        Booking::factory()->create(['created_at' => now()->subDays(2)]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $trend = (new AdminHomeDashboard)->bookingsTrend();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(7, $trend);
        $this->assertSame(2, $trend[6]['count'], 'today should count 2 bookings'); // last entry = today (daysAgo 0)
        $this->assertSame(1, $trend[4]['count'], '2 days ago should count 1 booking');

        $this->assertLessThanOrEqual(1, count($queries), 'trend must use a single grouped query');
    }
}
