<?php

namespace Tests\Feature\Safety;

use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use App\Services\Safety\UserSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserSafetyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_block_creates_user_block_idempotent(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $service = app(UserSafetyService::class);

        $block1 = $service->block($a, $b, 'spam');
        $block2 = $service->block($a, $b, 'other');   // 2e call

        $this->assertSame($block1->id, $block2->id);
        $this->assertSame(1, UserBlock::query()->count());
        $this->assertTrue($service->isBlocked($a, $b));
    }

    public function test_block_self_rejected(): void
    {
        $u = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(UserSafetyService::class)->block($u, $u);
    }

    public function test_unblock_removes_block(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $service = app(UserSafetyService::class);
        $service->block($a, $b);
        $service->unblock($a, $b);

        $this->assertFalse($service->isBlocked($a, $b));
    }

    public function test_is_mutually_blocked_detects_both_directions(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $service = app(UserSafetyService::class);

        $this->assertFalse($service->isMutuallyBlocked($a, $b));
        $service->block($a, $b);
        $this->assertTrue($service->isMutuallyBlocked($a, $b));
        $this->assertTrue($service->isMutuallyBlocked($b, $a));
    }

    public function test_report_creates_pending_record(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $report = app(UserSafetyService::class)->report($a, $b, 'harassment', 'Comportement inacceptable durant la mission.');

        $this->assertInstanceOf(UserReport::class, $report);
        $this->assertSame(UserReport::STATUS_PENDING, $report->status);
        $this->assertSame('harassment', $report->category);
    }

    public function test_report_invalid_category(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->expectException(ValidationException::class);
        app(UserSafetyService::class)->report($a, $b, 'invalid_cat', 'description longue');
    }

    public function test_report_idempotent_within_24h(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $service = app(UserSafetyService::class);

        $r1 = $service->report($a, $b, 'harassment', 'Premier signalement détaillé');
        $r2 = $service->report($a, $b, 'harassment', 'Deuxième tentative dans la même heure');

        $this->assertSame($r1->id, $r2->id);
    }

    public function test_resolve_report(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $admin = User::factory()->create();
        $service = app(UserSafetyService::class);
        $report = $service->report($a, $b, 'fraud', 'Detected duplicate accounts');

        $resolved = $service->resolveReport($report, $admin, UserReport::STATUS_RESOLVED_ACTION, 'Account suspended');

        $this->assertSame(UserReport::STATUS_RESOLVED_ACTION, $resolved->status);
        $this->assertSame($admin->id, (int) $resolved->reviewed_by_admin_id);
    }
}
