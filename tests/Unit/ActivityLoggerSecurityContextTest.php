<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActivityLoggerSecurityContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_stores_security_context_when_columns_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $zone = ServiceZone::factory()->create();
        $rdv = Booking::factory()->create([
            'service_zone_id' => $zone->id,
        ]);

        Route::middleware('web')->get('/_logger-test', function () use ($rdv) {
            ActivityLogger::critical('security.test_export', $rdv, [
                'domain' => 'security',
                'severity' => 'warning',
            ]);

            return 'ok';
        });

        $this->actingAs($admin)
            ->withHeader('X-Request-Id', 'req-sec-001')
            ->get('/_logger-test')
            ->assertOk();

        $log = ActivityLog::latest()->first();

        $this->assertNotNull($log);
        $this->assertSame('security', $log->domain);
        $this->assertSame('warning', $log->severity);
        $this->assertTrue((bool) $log->is_critical);
        $this->assertSame('req-sec-001', $log->request_id);
        $this->assertSame($zone->id, $log->service_zone_id);
    }
}
