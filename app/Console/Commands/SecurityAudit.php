<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Console\Command;

class SecurityAudit extends Command
{
    protected $signature = 'app:security-audit';

    protected $description = 'Audit simple de sécurité admin, scopes et permissions sensibles';

    public function handle(): int
    {
        $issues = 0;

        $admins = User::query()->where('role', User::ROLE_ADMIN)->get();

        foreach ($admins as $admin) {
            if (! $admin->is_active) {
                $issues++;
                $this->warn("Admin inactif détecté: {$admin->email}");
            }

            if ($admin->access_scope === User::ACCESS_SCOPE_ZONE && empty($admin->managed_service_zone_id)) {
                $issues++;
                $this->error("Admin zone sans zone gérée: {$admin->email}");
            }

            if ($admin->isReadOnlyAdmin() && $admin->canAccessAdminModule('perform-critical-admin-actions')) {
                $issues++;
                $this->warn("Admin lecture seule avec permission critique conservée: {$admin->email}");
            }
        }

        ActivityLogger::system('security.audit.executed', null, [
            'domain' => 'security',
            'severity' => $issues > 0 ? 'warning' : 'info',
            'is_critical' => $issues > 0,
            'issues' => $issues,
            'admins' => $admins->count(),
        ]);

        if ($issues === 0) {
            $this->info('Aucune anomalie de sécurité admin détectée.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("Audit terminé avec {$issues} anomalie(s).");

        return self::FAILURE;
    }
}
