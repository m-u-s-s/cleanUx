<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceDocumentService;
use Illuminate\Console\Command;

class SyncFinanceDocuments extends Command
{
    protected $signature = 'finance:sync-documents {--reminders : Envoie aussi les relances de factures}';

    protected $description = 'Synchronise les devis/factures à partir des rendez-vous et envoie les relances si demandé.';

    public function handle(FinanceDocumentService $service): int
    {
        $stats = $service->syncAllEligible();
        $this->info("Devis synchronisés : {$stats['quotes']}");
        $this->info("Factures synchronisées : {$stats['invoices']}");

        if ($this->option('reminders')) {
            $sent = $service->sendDueReminders();
            $this->info("Relances envoyées : {$sent}");
        }

        return self::SUCCESS;
    }
}
