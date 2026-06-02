<?php

namespace Tests\Unit;

use App\Services\Ops\ProductionHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProductionHealthReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_flags_http_app_url_in_production(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.key', 'base64:test-key');
        Config::set('app.url', 'http://cleanux.test');
        Config::set('queue.default', 'database');
        Config::set('cache.default', 'file');
        Config::set('session.driver', 'database');
        Config::set('mail.default', 'smtp');

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $httpsCheck = collect($report['checks'])->firstWhere('label', 'APP URL en HTTPS en production');

        $this->assertNotNull($httpsCheck);
        $this->assertFalse($httpsCheck['ok']);
        $this->assertSame('ERROR', $httpsCheck['severity']);
    }

    public function test_report_flags_mock_providers_as_error(): void
    {
        Config::set('kyc.default_provider', 'mock');

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Aucun provider en mode mock');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }

    public function test_report_flags_disabled_backups_as_error(): void
    {
        Config::set('operations.backups.enabled', false);

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Backups activés et configurés');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }

    public function test_report_flags_stale_heartbeat_as_error(): void
    {
        Config::set('operations.monitoring.heartbeat_enabled', true);
        Cache::forget(config('operations.monitoring.heartbeat_cache_key', 'cleanux:ops:heartbeat'));

        /** @var ProductionHealthReport $healthReport */
        $healthReport = app(ProductionHealthReport::class);

        $report = $healthReport->build();

        $check = collect($report['checks'])->firstWhere('label', 'Heartbeat récent');

        $this->assertNotNull($check);
        $this->assertFalse($check['ok']);
        $this->assertSame('ERROR', $check['severity']);
    }
}
