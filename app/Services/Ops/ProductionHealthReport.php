<?php

namespace App\Services\Ops;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductionHealthReport
{
    public function __construct(
        protected Filesystem $files,
        protected CacheRepository $cache,
        protected ConnectionInterface $db,
    ) {
    }

    /**
     * @return array{checks: array<int, array<string, mixed>>, metrics: array<string, mixed>}
     */
    public function build(): array
    {
        $appEnv = (string) config('app.env', 'production');
        $isProduction = $appEnv === 'production';
        $appUrl = (string) config('app.url', '');
        $queueDefault = (string) config('queue.default');
        $cacheDefault = (string) config('cache.default');
        $sessionDriver = (string) config('session.driver');
        $mailDefault = (string) config('mail.default');
        $checks = [];

        $heartbeat = $this->heartbeatSnapshot();
        $jobsTableExists = $this->safeHasTable('jobs');
        $failedJobsTableExists = $this->safeHasTable('failed_jobs');
        $sessionsTableExists = $this->safeHasTable('sessions');
        $cacheTableExists = $this->safeHasTable('cache');
        $queueBacklog = $jobsTableExists ? $this->safeTableCount('jobs') : null;
        $failedJobsCount = $failedJobsTableExists ? $this->safeTableCount('failed_jobs') : null;
        $queueBacklogThreshold = (int) config('operations.monitoring.queue_backlog_warning_threshold', 50);
        $failedJobsThreshold = (int) config('operations.monitoring.failed_jobs_warning_threshold', 1);

        $this->pushCheck($checks, 'APP key définie', filled(config('app.key')), 'error', filled(config('app.key')) ? 'OK' : 'Missing');
        $this->pushCheck($checks, 'APP debug désactivé en production', ! $isProduction || ! (bool) config('app.debug'), 'error', config('app.debug') ? 'true' : 'false');
        $this->pushCheck($checks, 'APP URL en HTTPS en production', ! $isProduction || ! config('operations.deployment.require_https_app_url', true) || str_starts_with($appUrl, 'https://'), 'error', $appUrl ?: 'missing');
        $this->pushCheck($checks, 'Queue non synchrone en production', ! $isProduction || $queueDefault !== 'sync', 'error', $queueDefault);
        $this->pushCheck($checks, 'Jobs queue présents si driver database', $queueDefault !== 'database' || $jobsTableExists, 'error', $jobsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Failed jobs présents si queue asynchrone', in_array($queueDefault, ['sync', 'null'], true) || $failedJobsTableExists, 'warning', $failedJobsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Backlog queue sous seuil', $queueBacklog === null || $queueBacklog <= $queueBacklogThreshold, 'warning', $queueBacklog === null ? 'n/a' : (string) $queueBacklog);
        $this->pushCheck($checks, 'Failed jobs sous seuil', $failedJobsCount === null || $failedJobsCount <= $failedJobsThreshold, 'warning', $failedJobsCount === null ? 'n/a' : (string) $failedJobsCount);
        $this->pushCheck($checks, 'Cache store adapté à la prod', ! $isProduction || ! in_array($cacheDefault, ['array', 'null'], true), 'warning', $cacheDefault);
        $this->pushCheck($checks, 'Cache table présente si driver database', $cacheDefault !== 'database' || $cacheTableExists, 'warning', $cacheTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Session driver adapté à la prod', ! $isProduction || ! in_array($sessionDriver, ['array'], true), 'warning', $sessionDriver);
        $this->pushCheck($checks, 'Sessions table présente si driver database', $sessionDriver !== 'database' || $sessionsTableExists, 'warning', $sessionsTableExists ? 'present' : 'missing');
        $this->pushCheck($checks, 'Cookie de session sécurisé en production HTTPS', ! $isProduction || ! str_starts_with($appUrl, 'https://') || config('session.secure') !== false, 'warning', var_export(config('session.secure'), true));
        $this->pushCheck($checks, 'Mailer configuré hors array/log en production', ! $isProduction || ! in_array($mailDefault, ['array', 'log'], true), 'warning', $mailDefault);
        $this->pushCheck($checks, 'Storage public lié', $this->storageLinkExists(), 'warning', $this->storageLinkExists() ? 'linked' : 'missing');
        $this->pushCheck($checks, 'Storage et bootstrap cache inscriptibles', $this->writablePathsOk(), 'warning', $this->writablePathsOk() ? 'writable' : 'check permissions');
        $this->pushCheck($checks, 'Heartbeat monitoring activé', (bool) config('operations.monitoring.heartbeat_enabled', true), 'warning', config('operations.monitoring.heartbeat_enabled') ? 'enabled' : 'disabled');
        $this->pushCheck($checks, 'Heartbeat récent', ! config('operations.monitoring.heartbeat_enabled', true) || ($heartbeat['exists'] && ($heartbeat['age_seconds'] ?? PHP_INT_MAX) <= (int) config('operations.monitoring.heartbeat_max_age_seconds', 900)), 'warning', $heartbeat['exists'] ? (string) ($heartbeat['age_seconds'] ?? 'unknown') : 'missing');
        $this->pushCheck($checks, 'Email de monitoring configuré', ! config('operations.monitoring.heartbeat_enabled', true) || filled(config('operations.monitoring.notify_email')), 'warning', (string) (config('operations.monitoring.notify_email') ?: 'missing'));
        $this->pushCheck($checks, 'Configuration backups présente', $this->backupConfigOk(), 'warning', config('operations.backups.enabled') ? 'configured' : 'disabled');
        $this->pushCheck($checks, 'Rétention backups positive', ! config('operations.backups.enabled', false) || (int) config('operations.backups.retention_days', 0) > 0, 'warning', (string) config('operations.backups.retention_days', 0));

        $metrics = [
            'app_env' => $appEnv,
            'app_url' => $appUrl,
            'queue' => $queueDefault,
            'queue_backlog' => $queueBacklog,
            'failed_jobs_count' => $failedJobsCount,
            'cache' => $cacheDefault,
            'session' => $sessionDriver,
            'mail' => $mailDefault,
            'filesystem' => (string) config('filesystems.default'),
            'heartbeat_cache_key' => (string) config('operations.monitoring.heartbeat_cache_key'),
            'heartbeat_age_seconds' => $heartbeat['age_seconds'],
            'heartbeat_source' => $heartbeat['source'],
            'backups_enabled' => (bool) config('operations.backups.enabled', false),
        ];

        return [
            'checks' => $checks,
            'metrics' => $metrics,
        ];
    }

    public function errorCount(array $report): int
    {
        return collect($report['checks'] ?? [])->where('severity', 'ERROR')->where('ok', false)->count();
    }

    public function warningCount(array $report): int
    {
        return collect($report['checks'] ?? [])->where('severity', 'WARNING')->where('ok', false)->count();
    }

    protected function pushCheck(array &$checks, string $label, bool $ok, string $severity, mixed $value = null): void
    {
        $checks[] = [
            'status' => $ok ? 'OK' : 'FAIL',
            'ok' => $ok,
            'severity' => strtoupper($severity),
            'label' => $label,
            'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
        ];
    }

    protected function storageLinkExists(): bool
    {
        $publicStorage = public_path('storage');

        return is_link($publicStorage) || is_dir($publicStorage);
    }

    protected function writablePathsOk(): bool
    {
        return is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
    }

    protected function backupConfigOk(): bool
    {
        if (! config('operations.backups.enabled', false)) {
            return true;
        }

        $disk = (string) config('operations.backups.disk', 'local');
        $path = trim((string) config('operations.backups.path', 'backups'));

        if ($path === '') {
            return false;
        }

        return Arr::has(config('filesystems.disks', []), $disk);
    }


    protected function safeHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function safeTableCount(string $table): ?int
    {
        try {
            return (int) $this->db->table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{exists: bool, age_seconds: ?int, source: string}
     */
    protected function heartbeatSnapshot(): array
    {
        $cacheKey = (string) config('operations.monitoring.heartbeat_cache_key', 'cleanux:ops:heartbeat');
        $cached = $this->cache->get($cacheKey);

        if (is_array($cached) && ! empty($cached['at'])) {
            return [
                'exists' => true,
                'age_seconds' => Carbon::parse($cached['at'])->diffInSeconds(now()),
                'source' => 'cache',
            ];
        }

        $disk = (string) config('operations.monitoring.heartbeat_disk', 'local');
        $path = (string) config('operations.monitoring.heartbeat_path', 'ops/heartbeat.json');

        if (Storage::disk($disk)->exists($path)) {
            $payload = json_decode((string) Storage::disk($disk)->get($path), true);

            if (is_array($payload) && ! empty($payload['at'])) {
                return [
                    'exists' => true,
                    'age_seconds' => Carbon::parse($payload['at'])->diffInSeconds(now()),
                    'source' => 'disk',
                ];
            }

            return [
                'exists' => true,
                'age_seconds' => null,
                'source' => 'disk',
            ];
        }

        return [
            'exists' => false,
            'age_seconds' => null,
            'source' => 'missing',
        ];
    }
}
