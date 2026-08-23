<?php

namespace App\Services\Contracts;

use App\Models\ContractSlaEvent;
use App\Models\Mission;
use App\Models\OrganizationContract;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ContractSlaService
{
    /** Arme le snapshot SLA d'une mission sous contrat (idempotent par mission+kind). */
    public function armForMission(Mission $mission): void
    {
        try {
            if (! $mission->organization_contract_id) {
                return;
            }

            $contract = OrganizationContract::find($mission->organization_contract_id);
            if (! $contract) {
                return;
            }

            $base = $mission->created_at ? Carbon::parse($mission->created_at) : now();
            $start = $mission->planned_start_at ? Carbon::parse($mission->planned_start_at) : $base;

            $responseDue = $contract->sla_response_hours ? $base->copy()->addHours((int) $contract->sla_response_hours) : null;
            $resolutionDue = $contract->sla_resolution_hours ? $start->copy()->addHours((int) $contract->sla_resolution_hours) : null;

            $mission->forceFill([
                'sla_response_due_at' => $responseDue,
                'sla_resolution_due_at' => $resolutionDue,
            ])->save();

            if ($responseDue) {
                ContractSlaEvent::updateOrCreate(
                    ['mission_id' => $mission->id, 'kind' => ContractSlaEvent::KIND_RESPONSE],
                    ['organization_contract_id' => $contract->id, 'due_at' => $responseDue, 'status' => ContractSlaEvent::STATUS_PENDING],
                );
            }
            if ($resolutionDue) {
                ContractSlaEvent::updateOrCreate(
                    ['mission_id' => $mission->id, 'kind' => ContractSlaEvent::KIND_RESOLUTION],
                    ['organization_contract_id' => $contract->id, 'due_at' => $resolutionDue, 'status' => ContractSlaEvent::STATUS_PENDING],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('SLA arming failed for mission '.$mission->id.': '.$e->getMessage());
        }
    }

    /** Scanne les événements SLA pending : met si satisfait avant échéance, breached/escalated si dépassé. */
    public function scan(): void
    {
        ContractSlaEvent::query()
            ->where('status', ContractSlaEvent::STATUS_PENDING)
            ->with('mission')
            ->chunkById(200, function ($events) {
                foreach ($events as $event) {
                    try {
                        $this->scanOne($event);
                    } catch (\Throwable $e) {
                        Log::warning('SLA scan failed for event '.$event->id.': '.$e->getMessage());
                    }
                }
            });
    }

    private function scanOne(ContractSlaEvent $event): void
    {
        $mission = $event->mission;
        if (! $mission) {
            return;
        }

        // Statuts réels du modèle Mission (App\Support\Domain\MissionStatus) :
        // planned / assigned / en_route / arrived / started / paused / completed / cancelled.
        // Response = le prestataire a pris la mission en main (tout statut au-delà de
        // planned, hors annulation). Resolution = mission terminée.
        $responseStatuses = [
            MissionStatus::ASSIGNED,
            MissionStatus::EN_ROUTE,
            MissionStatus::ARRIVED,
            MissionStatus::STARTED,
            MissionStatus::PAUSED,
            MissionStatus::COMPLETED,
        ];

        $satisfied = $event->kind === ContractSlaEvent::KIND_RESPONSE
            ? in_array($mission->status, $responseStatuses, true)
            : $mission->status === MissionStatus::COMPLETED || $mission->actual_end_at !== null;

        if ($satisfied) {
            $event->update(['status' => ContractSlaEvent::STATUS_MET]);

            return;
        }

        if (now()->greaterThan($event->due_at)) {
            $event->update([
                'status' => ContractSlaEvent::STATUS_ESCALATED,
                'breached_at' => $event->breached_at ?? now(),
                'escalated_at' => now(),
            ]);
            $this->escalate($event);
        }
    }

    private function escalate(ContractSlaEvent $event): void
    {
        // Réutilise le système de notifications existant (soft). À brancher sur
        // les responsables de l'org partenaire / cliente. Volontairement minimal.
        Log::info('SLA breach escalated', ['event_id' => $event->id, 'mission_id' => $event->mission_id, 'kind' => $event->kind]);
    }
}
