<?php

namespace App\Services\Gdpr;

use App\Models\GdprDataRequest;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

        // M14 — behavioural analytics retention (default ~14 months, GA-EU style).
        $analyticsDays = (int) config('gdpr.retention.analytics_events_days', 425);
        $stats['analytics_events'] = $this->purgeOlderThan('analytics_events', 'occurred_at', $analyticsDays);
        $stats['analytics_sessions'] = $this->purgeOlderThan('analytics_sessions', 'occurred_at', $analyticsDays);

        // M13 — delete expired GDPR export files (full PII dumps) from disk + null the path.
        $stats['gdpr_exports'] = $this->purgeExpiredExports();

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

    /**
     * Delete the on-disk export files for GdprDataRequests past their expires_at, and null the
     * stored path. The export JSON contains the user's full PII, so leaving it on a
     * signed-URL-accessible disk past the announced expiry breaks minimisation/retention.
     */
    protected function purgeExpiredExports(): int
    {
        if (! Schema::hasTable('gdpr_data_requests')) {
            return 0;
        }

        $disk = (string) config('gdpr.export_disk', 'local');
        $deleted = 0;

        GdprDataRequest::query()
            ->whereNotNull('export_file_path')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($requests) use ($disk, &$deleted) {
                foreach ($requests as $request) {
                    try {
                        if ($request->export_file_path && Storage::disk($disk)->exists($request->export_file_path)) {
                            Storage::disk($disk)->delete($request->export_file_path);
                        }
                        $request->forceFill(['export_file_path' => null])->save();
                        $deleted++;
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        return $deleted;
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
