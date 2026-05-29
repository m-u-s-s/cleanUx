<?php

namespace Tests\Feature\Nps;

use App\Models\NpsResponse;
use App\Models\User;
use App\Services\Nps\NpsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NpsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_creates_response_with_category(): void
    {
        $user = User::factory()->client()->create();

        $r = app(NpsService::class)->submit(
            user: $user,
            surveyCode: 'post_booking',
            score: 9,
            booking: null,
            comment: 'Très satisfait',
        );

        $this->assertInstanceOf(NpsResponse::class, $r);
        $this->assertSame(9, (int) $r->score);
        $this->assertSame(NpsResponse::CATEGORY_PROMOTER, $r->category);
    }

    public function test_submit_categorizes_passive(): void
    {
        $user = User::factory()->client()->create();
        $r = app(NpsService::class)->submit($user, 'post_booking', 7);
        $this->assertSame(NpsResponse::CATEGORY_PASSIVE, $r->category);
    }

    public function test_submit_categorizes_detractor(): void
    {
        $user = User::factory()->client()->create();
        $r = app(NpsService::class)->submit($user, 'post_booking', 3);
        $this->assertSame(NpsResponse::CATEGORY_DETRACTOR, $r->category);
    }

    public function test_submit_rejects_out_of_range_score(): void
    {
        $user = User::factory()->client()->create();
        $this->expectException(ValidationException::class);
        app(NpsService::class)->submit($user, 'post_booking', 11);
    }

    public function test_submit_idempotent_within_30_days(): void
    {
        $user = User::factory()->client()->create();
        $service = app(NpsService::class);

        $r1 = $service->submit($user, 'post_booking', 6);
        $r2 = $service->submit($user, 'post_booking', 9, comment: 'changed mind');

        $this->assertSame($r1->id, $r2->id);
        $this->assertSame(9, (int) $r2->fresh()->score);
        $this->assertSame(NpsResponse::CATEGORY_PROMOTER, $r2->fresh()->category);
    }

    public function test_calculate_returns_nps_score_promoters_minus_detractors(): void
    {
        $service = app(NpsService::class);
        for ($i = 0; $i < 4; $i++) {
            $u = User::factory()->client()->create();
            $service->submit($u, 'post_booking', 10);
        }
        for ($i = 0; $i < 2; $i++) {
            $u = User::factory()->client()->create();
            $service->submit($u, 'post_booking', 7);
        }
        for ($i = 0; $i < 1; $i++) {
            $u = User::factory()->client()->create();
            $service->submit($u, 'post_booking', 2);
        }

        // 7 total : 4 promoteurs, 2 passifs, 1 détracteur
        // NPS = (4/7 - 1/7) * 100 = (57.14 - 14.28) = 42.86 ≈ 42.9
        $result = $service->calculate(Carbon::now()->subDays(1), Carbon::now()->addMinute());

        $this->assertSame(7, $result['total']);
        $this->assertSame(4, $result['promoters']);
        $this->assertSame(2, $result['passives']);
        $this->assertSame(1, $result['detractors']);
        $this->assertGreaterThanOrEqual(40, $result['nps']);
        $this->assertLessThanOrEqual(45, $result['nps']);
    }
}
