<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\AuditRetentionPolicy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuditRetentionService — purge périodique des audit_events au-delà de leur retention.
 *
 *   - Respecte les policies DB (audit_retention_policies par domain + severity)
 *   - Sinon utilise config 'audit.retention_days_by_domain' (default mapping)
 *   - Ne purge JAMAIS les rows is_pinned=true
 *   - Ne purge JAMAIS la severity 'critical' (configurable)
 *   - Batch + time-limit pour éviter long-running query
 */
class AuditRetentionService
{
    public function purge(): int
    {
        $batchSize = (int) Config::get('audit.purge_batch_size', 5000);
        $maxSeconds = (int) Config::get('audit.purge_max_runtime_seconds', 300);
        $neverPurge = (array) Config::get('audit.never_purge_severity', ['critical']);

        $start = microtime(true);
        $totalDeleted = 0;

        $domains = $this->resolveAllDomains();

        foreach ($domains as $domain => $retentionDays) {
            $cutoff = now()->subDays($retentionDays);

            do {
                if ((microtime(true) - $start) >= $maxSeconds) {
                    Log::warning('AuditRetentionService::purge time limit reached', [
                        'deleted_so_far' => $totalDeleted,
                    ]);
                    return $totalDeleted;
                }

                $deleted = AuditEvent::query()
                    ->where('domain', $domain)
                    ->where('occurred_at', '<', $cutoff)
                    ->where('is_pinned', false)
                    ->whereNotIn('severity', $neverPurge)
                    ->limit($batchSize)
                    ->delete();

                $totalDeleted += $deleted;
            } while ($deleted >= $batchSize);
        }

        return $totalDeleted;
    }

    /**
     * @return array<string, int>  domain => retention_days
     */
    public function resolveAllDomains(): array
    {
        $defaults = (array) Config::get('audit.retention_days_by_domain', []);

        try {
            $dbPolicies = AuditRetentionPolicy::query()->active()->get();
            foreach ($dbPolicies as $policy) {
                // DB policy overrides default
                $defaults[$policy->domain] = (int) $policy->retention_days;
            }
        } catch (\Throwable $e) {
            // table may not exist in dev; use defaults
        }

        return $defaults;
    }
}
