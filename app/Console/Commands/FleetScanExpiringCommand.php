<?php

namespace App\Console\Commands;

use App\Services\FleetV2\CertificationExpiryScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FleetScanExpiringCommand extends Command
{
    protected $signature = 'fleet:scan-expiring
        {--days= : Override config expiring_soon_days threshold}
        {--show : Display rows expiring soon}';

    protected $description = 'Scan fleet certifications and update their status (expired/expiring_soon/active)';

    public function handle(CertificationExpiryScanner $scanner): int
    {
        if (! config('fleet_v2.enabled', true)) {
            $this->warn('Fleet v2 disabled. Skip.');
            return self::SUCCESS;
        }
        if (! Schema::hasTable('fleet_certifications')) {
            $this->warn('fleet_certifications table missing. Skip.');
            return self::SUCCESS;
        }

        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $counts = $scanner->scanAndUpdate($days);

        $this->info(sprintf(
            'Scan complete: %d expired, %d expiring_soon, %d reactivated.',
            $counts['expired'], $counts['expiring_soon'], $counts['reactivated'],
        ));

        if ($this->option('show')) {
            $rows = $scanner->listExpiringSoon($days);
            foreach ($rows as $cert) {
                $this->line(sprintf(
                    '  - cert#%d %s on %s#%d expires %s',
                    $cert->id, $cert->certification_type, $cert->subject_type, $cert->subject_id,
                    $cert->expires_at?->format('Y-m-d') ?? 'n/a',
                ));
            }
        }

        return self::SUCCESS;
    }
}
