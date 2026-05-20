<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetCertification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FleetScanExpiringCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fleet_v2.enabled', true);
        Config::set('fleet_v2.expiring_soon_days', 30);
    }

    public function test_command_updates_certifications_status(): void
    {
        FleetCertification::query()->create([
            'subject_type' => 'vehicle', 'subject_id' => 1,
            'certification_type' => 'insurance',
            'expires_at' => now()->subDay(), 'status' => 'active',
        ]);
        FleetCertification::query()->create([
            'subject_type' => 'vehicle', 'subject_id' => 2,
            'certification_type' => 'control_technique',
            'expires_at' => now()->addDays(15), 'status' => 'active',
        ]);

        $this->artisan('fleet:scan-expiring')
            ->expectsOutputToContain('Scan complete')
            ->assertSuccessful();

        $this->assertSame(1, FleetCertification::query()->where('status', 'expired')->count());
        $this->assertSame(1, FleetCertification::query()->where('status', 'expiring_soon')->count());
    }

    public function test_command_skips_when_module_disabled(): void
    {
        Config::set('fleet_v2.enabled', false);
        $this->artisan('fleet:scan-expiring')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }
}
