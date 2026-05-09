<?php

namespace App\Jobs\Dispatch;

use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11 — Job de timeout d'une offre de mission.
 *
 * Dispatché par MissionDispatchService::createOffer() avec ->delay($expiresAt).
 *
 * À l'exécution :
 *   1. Recharge l'assignment depuis la DB
 *   2. Si encore "assigned" et expiré → expireAndEscalate()
 *   3. Sinon (déjà accepté/refusé/expiré) → ne rien faire
 *
 * Robustesse :
 *   - Si l'assignment n'existe plus, on logue et on stoppe (pas de retry)
 *   - tries=1 : pas de retry (l'escalation se déclenche elle-même un nouveau job
 *     pour le prochain prestataire)
 */
class EscalateMissionAssignmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public int $assignmentId,
    ) {}

    public function handle(MissionDispatchService $service): void
    {
        $assignment = MissionAssignment::find($this->assignmentId);

        if (! $assignment) {
            Log::info('EscalateMissionAssignmentJob: assignment introuvable', [
                'assignment_id' => $this->assignmentId,
            ]);
            return;
        }

        $service->expireAndEscalate($assignment);
    }

    /**
     * Tag pour Horizon : permet de filtrer les jobs de dispatch dans le dashboard.
     */
    public function tags(): array
    {
        return ['dispatch', 'mission-assignment-' . $this->assignmentId];
    }
}
