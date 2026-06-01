<?php

namespace App\Console\Commands;

use App\Services\Contracts\ContractSlaService;
use Illuminate\Console\Command;

class ScanContractSla extends Command
{
    protected $signature = 'contract:scan-sla';

    protected $description = 'Scan contract SLA events: mark met / breached and escalate once.';

    public function handle(ContractSlaService $service): int
    {
        $service->scan();
        $this->info('Contract SLA scan complete.');

        return self::SUCCESS;
    }
}
