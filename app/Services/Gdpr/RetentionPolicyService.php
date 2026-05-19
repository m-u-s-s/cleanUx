<?php

namespace App\Services\Gdpr;

use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RetentionPolicyService
{
    /**
     * @return array<string,int> table => rows purged
     */
    public function enforceAll(): array
    {
        $stats = [];

        $stats['activity_logs'] = $this->purgeOlderThan(
            'activity_logs',
            'created_at',
            (int) config('gdpr.retention.activity_logs_days', 730),
        );

        $stats['notifications'] = $this->purgeNotifications();

        $stats['sessions'] = $this->purgeOlderThan(
            'sessions',
            'last_activity',
            (int) config('gdpr.retention.sessions_days', 90),
            isUnixTimestamp: true,
        );

        $stats['failed_jobs'] = $this->purgeOlderThan(
            'failed_jobs',
            'failed_at',
            (int) config('gdpr.retention.failed_jobs_days', 90),
        );

        ActivityLogger::system('gdpr.retention_enforced', null, [
            'stats' => $stats,
        ]);

        return $stats;
    }

    protected function purgeOlderThan(string $table, string $column, int $days, bool $isUnixTimestamp = false): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        if (! Schema::hasColumn($table, $column)) {
            return 0;
        }

        $cutoff = $isUnixTimestamp
            ? now()->subDays($days)->getTimestamp()
            : now()->subDays($days);

        try {
            return DB::table($table)
                ->where($column, '<', $cutoff)
                ->delete();
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
    }

    protected function purgeNotifications(): int
    {
        $days = (int) config('gdpr.retention.notifications_days', 365);
        if (! Schema::hasTable('notifications')) {
            return 0;
        }

        $cutoff = now()->subDays($days);

        try {
            return DB::table('notifications')
                ->whereNotNull('read_at')
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
    }
}
