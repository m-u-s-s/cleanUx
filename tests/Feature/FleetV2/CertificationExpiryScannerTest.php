<?php

namespace Tests\Feature\FleetV2;

use App\Models\FleetCertification;
use App\Services\FleetV2\CertificationExpiryScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CertificationExpiryScannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('fleet_v2.expiring_soon_days', 30);
    }

    private function cert(?\DateTimeInterface $expires = null, string $status = 'active'): FleetCertification
    {
        return FleetCertification::query()->create([
            'subject_type' => FleetCertification::SUBJECT_VEHICLE,
            'subject_id' => random_int(1000, 9999),
            'certification_type' => 'insurance',
            'expires_at' => $expires,
            'status' => $status,
        ]);
    }

    public function test_scan_marks_past_expiry_as_expired(): void
    {
        $c = $this->cert(now()->subDay());
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_EXPIRED, $c->fresh()->status);
    }

    public function test_scan_marks_imminent_as_expiring_soon(): void
    {
        $c = $this->cert(now()->addDays(15));
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_EXPIRING_SOON, $c->fresh()->status);
    }

    public function test_scan_keeps_far_future_active(): void
    {
        $c = $this->cert(now()->addYear());
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_ACTIVE, $c->fresh()->status);
    }

    public function test_scan_ignores_revoked(): void
    {
        $c = $this->cert(now()->subDay(), 'revoked');
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame('revoked', $c->fresh()->status);
    }

    public function test_scan_reactivates_when_renewed(): void
    {
        $c = $this->cert(now()->addDays(15), 'expiring_soon');
        // Renouvellement
        $c->update(['expires_at' => now()->addYear()]);
        app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(FleetCertification::STATUS_ACTIVE, $c->fresh()->status);
    }

    public function test_list_expiring_soon_returns_window(): void
    {
        $this->cert(now()->addDays(10));
        $this->cert(now()->addDays(60));  // hors fenêtre
        $rows = app(CertificationExpiryScanner::class)->listExpiringSoon(30);
        $this->assertCount(1, $rows);
    }

    public function test_scan_returns_counts(): void
    {
        $this->cert(now()->subDay());
        $this->cert(now()->addDays(10));
        $this->cert(now()->addYear());

        $counts = app(CertificationExpiryScanner::class)->scanAndUpdate();
        $this->assertSame(1, $counts['expired']);
        $this->assertSame(1, $counts['expiring_soon']);
    }
}
