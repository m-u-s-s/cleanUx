<?php

namespace Tests\Unit;

use App\Services\Ops\ProductionHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
