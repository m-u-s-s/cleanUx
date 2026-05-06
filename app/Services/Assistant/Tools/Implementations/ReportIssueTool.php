<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

/**
 * Phase 5.1 — Tool: signaler un incident sur une mission en cours.
 *
 * Tous types d'utilisateurs peuvent l'appeler (client, prestataire, admin),
 * mais seuls les utilisateurs liés à la mission peuvent signaler dessus.
 */
class ReportIssueTool implements AssistantTool
{
    public function name(): string
    {
        return 'report_issue';
    }

    public function description(): string
    {
        return "Signale un incident sur une mission en cours ou récente "
            . "(retard, équipement défectueux, accès refusé, conflit, dégâts, etc.). "
            . "L'utilisateur doit avoir un lien avec la mission (client, prestataire, ou membre de l'org).";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'mission_id' => [
                    'type'        => 'integer',
                    'description' => "ID de la mission concernée.",
                ],
                'severity' => [
                    'type'        => 'string',
                    'enum'        => ['low', 'medium', 'high', 'critical'],
                    'description' => "Gravité estimée. 'critical' = bloque la mission ou risque de dommage.",
                ],
                'category' => [
                    'type'        => 'string',
                    'enum'        => ['delay', 'equipment', 'access', 'damage', 'conflict', 'safety', 'quality', 'other'],
                    'description' => "Catégorie de l'incident.",
                ],
                'description' => [
                    'type'        => 'string',
                    'maxLength'   => 2000,
                    'description' => "Description détaillée de l'incident, faits objectifs, heure, contexte.",
                ],
            ],
            'required' => ['mission_id', 'severity', 'category', 'description'],
        ];
    }

    public function authorize(User $user): bool
    {
        return true; // ownership check fait dans execute()
    }

    public function executesImmediately(): bool
    {
        return false;
    }

    public function execute(User $user, array $input): array
    {
        $mission = Mission::find($input['mission_id']);

        if (! $mission) {
            return ['ok' => false, 'error' => "Mission #{$input['mission_id']} introuvable."];
        }

        // Vérification d'accès : user doit être lié à la mission
        $hasAccess = false;

        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            $hasAccess = true;
        } elseif ((int) $mission->lead_employee_id === (int) $user->id) {
            $hasAccess = true;
        } elseif ((int) ($mission->rendezVous?->customer_user_id ?? 0) === (int) $user->id) {
            $hasAccess = true;
        } elseif (
            $mission->organization_account_id
            && $user->organization_account_id
            && (int) $mission->organization_account_id === (int) $user->organization_account_id
        ) {
            $hasAccess = true;
        } elseif ($mission->assignments()->where('user_id', $user->id)->exists()) {
            $hasAccess = true;
        }

        if (! $hasAccess) {
            return ['ok' => false, 'error' => "Vous n'avez pas accès à cette mission."];
        }

        $incident = MissionIncident::create([
            'mission_id'      => $mission->id,
            'reported_by'     => $user->id,
            'severity'        => $input['severity'],
            'category'        => $input['category'],
            'description'     => $input['description'],
            'status'          => 'open',
            'reported_at'     => now(),
        ]);

        return [
            'ok'           => true,
            'incident_id'  => $incident->id,
            'mission_id'   => $mission->id,
            'severity'     => $input['severity'],
            'message'      => "Incident #{$incident->id} signalé. Notre équipe support va le traiter.",
        ];
    }
}
