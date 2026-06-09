<?php

namespace Tests\Unit\Finance;

use App\Models\Booking;
use App\Models\Mission;
use App\Services\Finance\MissionProfitabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MissionProfitabilityServiceCoverageBatch18Test extends TestCase
{
    use RefreshDatabase;

    private function makeService(): MissionProfitabilityService
    {
        return app(MissionProfitabilityService::class);
    }

    #[Test]
    public function it_computes_an_excellent_margin_using_real_minutes(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 200]);

        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'planned_start_at' => Carbon::parse('2026-01-01 09:00:00'),
            'planned_end_at' => Carbon::parse('2026-01-01 11:00:00'),
            'actual_start_at' => Carbon::parse('2026-01-01 09:00:00'),
            'actual_end_at' => Carbon::parse('2026-01-01 10:00:00'),
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertSame(200.0, $result['price']);
        $this->assertEqualsWithDelta(120, $result['planned_minutes'], 0.001);
        $this->assertEqualsWithDelta(60, $result['real_minutes'], 0.001);
        $this->assertEqualsWithDelta(60, $result['work_minutes_used'], 0.001);
        $this->assertSame(18.0, $result['employee_cost']);
        $this->assertSame(8.0, $result['travel_cost']);
        $this->assertSame(16.0, $result['material_cost']);
        $this->assertSame(42.0, $result['total_cost']);
        $this->assertSame(158.0, $result['gross_margin']);
        $this->assertSame(79.0, $result['margin_rate']);
        $this->assertSame('excellent', $result['status']);
    }

    #[Test]
    public function it_classifies_a_good_margin(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 100]);

        // real minutes = 180 -> employee 54 + travel 8 + material 8 = 70 ; margin 30 -> 30% good
        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'actual_start_at' => Carbon::parse('2026-02-01 08:00:00'),
            'actual_end_at' => Carbon::parse('2026-02-01 11:00:00'),
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertEqualsWithDelta(180, $result['real_minutes'], 0.001);
        $this->assertSame(30.0, $result['margin_rate']);
        $this->assertSame('good', $result['status']);
    }

    #[Test]
    public function it_classifies_a_warning_margin(): void
    {
        $booking = Booking::factory()->create(['devis_estime' => 100]);

        // real minutes = 210 -> employee 63 + 8 + 8 = 79 ; margin 21 -> 21% warning
        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'actual_start_at' => Carbon::parse('2026-02-01 08:00:00'),
            'actual_end_at' => Carbon::parse('2026-02-01 11:30:00'),
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertEqualsWithDelta(210, $result['real_minutes'], 0.001);
        $this->assertSame(21.0, $result['margin_rate']);
        $this->assertSame('warning', $result['status']);
    }

    #[Test]
    public function it_classifies_a_critical_margin_when_price_is_zero(): void
    {
        $booking = Booking::factory()->create();
        // Strip every price source so the `?? 0` fallback is exercised. The saving hook
        // re-syncs devis_estime from estimated_price, so both columns must be cleared.
        $booking->devis_estime = null;
        $booking->estimated_price = null;
        $booking->pricing_snapshot = null;
        $booking->duree_estimee = 75;
        $booking->save();

        // No actual times -> real_minutes null -> work falls back to planned minutes,
        // and with planned times nulled it falls back to duree_estimee (75).
        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'planned_start_at' => null,
            'planned_end_at' => null,
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertSame(0.0, $result['price']);
        $this->assertNull($result['real_minutes']);
        $this->assertSame(75, $result['planned_minutes']);
        $this->assertSame(75, $result['work_minutes_used']);
        $this->assertSame(0, $result['margin_rate']);
        $this->assertSame('critical', $result['status']);
    }

    #[Test]
    public function it_falls_back_to_the_pricing_snapshot_for_the_price(): void
    {
        $booking = Booking::factory()->create();
        $booking->devis_estime = null;
        $booking->estimated_price = null;
        $booking->pricing_snapshot = ['devis_estime' => 150];
        $booking->save();

        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'planned_start_at' => Carbon::parse('2026-03-01 09:00:00'),
            'planned_end_at' => Carbon::parse('2026-03-01 10:00:00'),
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertSame(150.0, $result['price']);
        // No actual times -> work minutes derive from planned diff (60).
        $this->assertNull($result['real_minutes']);
        $this->assertEqualsWithDelta(60, $result['planned_minutes'], 0.001);
        $this->assertEqualsWithDelta(60, $result['work_minutes_used'], 0.001);
    }

    #[Test]
    public function it_uses_duree_when_duree_estimee_is_missing(): void
    {
        $booking = Booking::factory()->create();
        $booking->duree_estimee = null;
        $booking->estimated_duration_minutes = null;
        $booking->duree = 50;
        $booking->save();

        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'planned_start_at' => null,
            'planned_end_at' => null,
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertSame(50, $result['planned_minutes']);
    }

    #[Test]
    public function it_defaults_planned_minutes_to_ninety_without_any_duration(): void
    {
        $booking = Booking::factory()->create();
        $booking->duree_estimee = null;
        $booking->estimated_duration_minutes = null;
        $booking->duree = null;
        $booking->save();

        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'planned_start_at' => null,
            'planned_end_at' => null,
            'actual_start_at' => null,
            'actual_end_at' => null,
        ]);

        $result = $this->makeService()->calculate($mission);

        $this->assertSame(90, $result['planned_minutes']);
    }
}
