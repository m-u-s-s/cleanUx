<?php

namespace App\Support\Audit;

use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Helper soft-fail pour émettre des AuditEvent depuis les services v2
 * sur les actions critiques (clôture compta, suspension tenant, etc.).
 *
 * Skip silencieusement si :
 *  - Module audit v2 désactivé (config audit.enabled=false)
 *  - Table audit_events absente
 *  - AuditService non bindé
 */
class CriticalActionAuditor
{
    public static function record(
        string $eventType,
        array $context = [],
        ?object $subject = null,
        ?object $actor = null,
        string $severity = 'info',
    ): void {
        try {
            if (! Schema::hasTable('audit_events')) {
                return;
            }
            app(AuditService::class)->record(
                eventType: $eventType,
                context: $context,
                options: array_filter([
                    'subject' => $subject,
                    'actor' => $actor,
                    'severity' => $severity,
                ]),
            );
        } catch (\Throwable $e) {
            Log::warning('[critical_audit] failed', [
                'event' => $eventType, 'error' => $e->getMessage(),
            ]);
        }
    }
}
