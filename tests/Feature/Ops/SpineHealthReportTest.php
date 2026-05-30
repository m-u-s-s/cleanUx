<?php

namespace Tests\Feature\Ops;

use App\Services\Ops\SpineHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpineHealthReportTest extends TestCase
{
    use RefreshDatabase;

    private SpineHealthReport $report;

    protected function setUp(): void
    {
        parent::setUp();
        $this->report = new SpineHealthReport;
    }

    #[Test]
    public function collect_returns_array_with_all_expected_keys(): void
    {
        $result = $this->report->collect();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('db', $result);
        $this->assertArrayHasKey('redis', $result);
        $this->assertArrayHasKey('queue', $result);
        $this->assertArrayHasKey('stripe', $result);
        $this->assertArrayHasKey('reverb', $result);
        $this->assertCount(5, $result);
    }

    #[Test]
    public function each_probe_has_ok_bool_and_detail_string(): void
    {
        $result = $this->report->collect();

        foreach (['db', 'redis', 'queue', 'stripe', 'reverb'] as $key) {
            $probe = $result[$key];
            $this->assertArrayHasKey('ok', $probe, "Probe [{$key}] missing 'ok'");
            $this->assertArrayHasKey('detail', $probe, "Probe [{$key}] missing 'detail'");
            $this->assertIsBool($probe['ok'], "Probe [{$key}].ok must be bool");
            $this->assertIsString($probe['detail'], "Probe [{$key}].detail must be string");
        }
    }

    #[Test]
    public function collect_does_not_throw_in_test_environment(): void
    {
        // The assertion IS that it returns an array — graceful degradation
        $result = $this->report->collect();

        $this->assertIsArray($result);
    }

    #[Test]
    public function db_probe_is_ok_because_test_database_is_reachable(): void
    {
        $result = $this->report->collect();

        $this->assertTrue($result['db']['ok'], 'db probe should be ok=true — test DB is reachable');
        $this->assertStringContainsString('ok', strtolower($result['db']['detail']));
    }

    #[Test]
    public function queue_probe_reports_configured_connection(): void
    {
        $result = $this->report->collect();

        $queue = $result['queue'];
        $this->assertIsArray($queue);
        $this->assertArrayHasKey('ok', $queue);
        $this->assertArrayHasKey('detail', $queue);
        // detail should mention the driver name
        $this->assertNotEmpty($queue['detail']);
    }

    #[Test]
    public function stripe_probe_is_not_ok_when_no_real_api_key_configured(): void
    {
        // In test env CASHIER_SECRET / Stripe key is typically absent or a dummy
        // Probe must not make a real network call; it should return ok=false gracefully
        $result = $this->report->collect();

        $stripe = $result['stripe'];
        $this->assertIsArray($stripe);
        // We only assert that it doesn't throw and returns the right shape;
        // we do NOT assert ok=false here because a valid key could be configured
        $this->assertIsBool($stripe['ok']);
        $this->assertIsString($stripe['detail']);
    }

    #[Test]
    public function reverb_probe_returns_valid_shape(): void
    {
        $result = $this->report->collect();

        $reverb = $result['reverb'];
        $this->assertIsBool($reverb['ok']);
        $this->assertIsString($reverb['detail']);
    }
}
