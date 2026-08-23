<?php

namespace App\Console\Commands;

use App\Services\Onboarding\ProviderDocumentExpiryScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Le préavis de péremption des justificatifs prestataire. */
class ProviderDocumentsScanExpiringCommand extends Command
{
    protected $signature = 'provider:scan-expiring-documents
        {--days= : Remplace le préavis de config (onboarding_documents.expiring_soon_days)}';

    protected $description = 'Prévient les prestataires dont un justificatif approuvé arrive à échéance';

    public function handle(ProviderDocumentExpiryScanner $scanner): int
    {
        if (! Schema::hasTable('provider_onboarding_documents')) {
            $this->warn('Table provider_onboarding_documents absente. Ignoré.');

            return self::SUCCESS;
        }

        $jours = $this->option('days') !== null ? (int) $this->option('days') : null;
        $compte = $scanner->scanAndNotify($jours);

        $this->info(sprintf(
            'Scan terminé : %d prévenu(s), %d déjà périmé(s), %d ignoré(s).',
            $compte['notified'],
            $compte['expired'],
            $compte['skipped'],
        ));

        return self::SUCCESS;
    }
}
