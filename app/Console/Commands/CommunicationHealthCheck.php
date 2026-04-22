<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\AdminDigestNotification;
use App\Services\Integrations\GoogleCalendarSyncService;
use Illuminate\Console\Command;

class CommunicationHealthCheck extends Command
{
    protected $signature = 'app:communication-health-check {--stale-hours=24 : Nombre d\'heures avant qu\'une connexion soit considérée comme stale}';
    protected $description = 'Audit des communications : notifications, emails métier et synchronisation calendrier';

    public function handle(GoogleCalendarSyncService $syncService): int
    {
        $summary = $syncService->healthSummary((int) $this->option('stale-hours'));
        $items = [];

        if (($summary['stale_connections'] ?? 0) > 0) {
            $items[] = $summary['stale_connections'] . ' connexion(s) Google Agenda n’ont pas été synchronisées récemment.';
        }

        if (($summary['error_connections'] ?? 0) > 0) {
            $items[] = $summary['error_connections'] . ' connexion(s) Google Agenda sont en erreur.';
        }

        if (($summary['expired_tokens'] ?? 0) > 0) {
            $items[] = $summary['expired_tokens'] . ' token(s) Google sont expirés ou incomplets.';
        }

        if (($summary['failed_event_links'] ?? 0) > 0) {
            $items[] = $summary['failed_event_links'] . ' événement(s) Google liés à des RDV sont marqués en échec.';
        }

        if ($items === []) {
            $this->info('Aucun problème de communication détecté.');
            return self::SUCCESS;
        }

        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(new AdminDigestNotification(
                $items,
                'Health check communication',
                url('/admin/integrations/google-agenda')
            ));
        }

        if (class_exists(ActivityLog::class)) {
            ActivityLog::query()->create([
                'user_id' => null,
                'action' => 'communication.health_check.detected_issues',
                'target_type' => 'platform',
                'target_id' => null,
                'meta' => $summary,
            ]);
        }

        $this->warn('Des problèmes de communication ont été détectés et notifiés.');

        return self::SUCCESS;
    }
}
