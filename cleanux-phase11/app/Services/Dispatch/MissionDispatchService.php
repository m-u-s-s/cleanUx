<?php

namespace App\Services\Dispatch;

use App\Jobs\Dispatch\EscalateMissionAssignmentJob;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use App\Notifications\Dispatch\MissionOfferNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11 — Orchestrateur du dispatch d'une mission vers un prestataire.
 *
 * Inspiré du dispatch Uber :
 *   1. createOffer(Mission, User) crée un MissionAssignment "pending"
 *   2. Notification push envoyée au prestataire (15s pour répondre)
 *   3. Job de timeout planifié à T+15s
 *   4. Si accept → mission devient "assigned" + autres assignments cancellés
 *   5. Si decline → on escalade au suivant via AiDispatchService
 *   6. Si timeout (job qui s'exécute) → idem decline
 *
 * Cette classe ne sait PAS qui choisir comme prestataire — elle s'appuie sur
 * AiDispatchService::rankEmployees() pour la liste classée par score.
 */
class MissionDispatchService
{
    public const RESPONSE_TIMEOUT_SECONDS = 15;

    /** Liste des candidats déjà tentés pour cette mission (anti-boucle). */
    public function __construct(
        protected AiDispatchService $aiDispatch,
    ) {}

    /**
     * Lance le dispatch d'une mission au top scorer disponible.
     *
     * Si previousAssignmentId est fourni, on enchaîne en escalation
     * et on exclut le prestataire qui vient de refuser.
     *
     * Renvoie le nouvel assignment, ou null si aucun candidat disponible.
     */
    public function dispatchToNextProvider(
        Mission $mission,
        ?int $previousAssignmentId = null,
    ): ?MissionAssignment {
        // Récupère la liste classée des candidats via le service existant
        $booking = $mission->booking;
        if (! $booking) {
            Log::warning('MissionDispatchService: mission sans booking', [
                'mission_id' => $mission->id,
            ]);
            return null;
        }

        $ranked = $this->aiDispatch->rankEmployees($booking);
        if ($ranked->isEmpty()) {
            Log::info('MissionDispatchService: aucun candidat trouvé', [
                'mission_id' => $mission->id,
            ]);
            return null;
        }

        // Exclure les prestataires déjà tentés pour cette mission
        $alreadyTried = $mission->assignments()
            ->pluck('user_id')
            ->all();

        $candidate = $ranked
            ->reject(fn ($entry) => in_array($entry['employee']->id, $alreadyTried, true))
            ->first();

        if (! $candidate) {
            Log::info('MissionDispatchService: tous les candidats déjà tentés', [
                'mission_id'      => $mission->id,
                'tried_user_ids'  => $alreadyTried,
            ]);
            return null;
        }

        return $this->createOffer($mission, $candidate['employee'], $previousAssignmentId);
    }

    /**
     * Crée une offre (MissionAssignment "assigned" en attente d'accept) pour un
     * prestataire précis. Notifie le prestataire et planifie l'escalation.
     */
    public function createOffer(
        Mission $mission,
        User $provider,
        ?int $previousAssignmentId = null,
    ): MissionAssignment {
        return DB::transaction(function () use ($mission, $provider, $previousAssignmentId) {
            $now = now();
            $expiresAt = $now->copy()->addSeconds(self::RESPONSE_TIMEOUT_SECONDS);

            $assignment = MissionAssignment::create([
                'mission_id'                    => $mission->id,
                'user_id'                       => $provider->id,
                'role_on_mission'               => 'lead',
                'assignment_status'             => 'assigned',
                'assigned_at'                   => $now,
                'notification_sent_at'          => $now,
                'expires_at'                    => $expiresAt,
                'escalated_from_assignment_id'  => $previousAssignmentId,
            ]);

            // Notification push (canal database + mail + webpush via Phase 8)
            try {
                $provider->notify(new MissionOfferNotification($mission, $assignment));
            } catch (\Throwable $e) {
                Log::warning('Échec notification dispatch', [
                    'assignment_id' => $assignment->id,
                    'error'         => $e->getMessage(),
                ]);
            }

            // Schedule the escalation job
            EscalateMissionAssignmentJob::dispatch($assignment->id)
                ->delay($expiresAt);

            Log::info('MissionDispatchService: offre créée', [
                'assignment_id' => $assignment->id,
                'mission_id'    => $mission->id,
                'provider_id'   => $provider->id,
                'expires_at'    => $expiresAt->toIso8601String(),
            ]);

            return $assignment;
        });
    }

    /**
     * Le prestataire accepte l'offre. Marque l'assignment, marque la mission
     * comme "assigned", annule les autres offres en cours pour cette mission.
     */
    public function accept(MissionAssignment $assignment): MissionAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment->refresh();

            $this->guardAcceptable($assignment);

            $now = now();
            $responseSeconds = $assignment->notification_sent_at
                ? max(0, (int) $now->diffInSeconds($assignment->notification_sent_at))
                : null;

            $assignment->update([
                'assignment_status' => 'accepted',
                'accepted_at'       => $now,
                'response_seconds'  => $responseSeconds,
            ]);

            // Mission : passe à "assigned" si elle ne l'est pas déjà
            $mission = $assignment->mission;
            if ($mission && $mission->status === 'planned') {
                $mission->update([
                    'status'                => 'assigned',
                    'lead_provider_user_id' => $assignment->user_id,
                ]);
            }

            // Annuler les autres assignments en cours pour cette mission (au cas où)
            MissionAssignment::where('mission_id', $assignment->mission_id)
                ->where('id', '!=', $assignment->id)
                ->where('assignment_status', 'assigned')
                ->update([
                    'assignment_status' => 'cancelled',
                    'declined_at'       => $now,
                    'decline_reason'    => 'Autre prestataire a accepté en premier',
                ]);

            Log::info('MissionDispatchService: assignment accepté', [
                'assignment_id'    => $assignment->id,
                'response_seconds' => $responseSeconds,
            ]);

            return $assignment->fresh();
        });
    }

    /**
     * Le prestataire refuse l'offre. Lance immédiatement l'escalation au suivant.
     */
    public function decline(MissionAssignment $assignment, ?string $reason = null): ?MissionAssignment
    {
        return DB::transaction(function () use ($assignment, $reason) {
            $assignment->refresh();

            $this->guardDeclinable($assignment);

            $now = now();
            $responseSeconds = $assignment->notification_sent_at
                ? max(0, (int) $now->diffInSeconds($assignment->notification_sent_at))
                : null;

            $assignment->update([
                'assignment_status' => 'declined',
                'declined_at'       => $now,
                'decline_reason'    => $reason,
                'response_seconds'  => $responseSeconds,
            ]);

            Log::info('MissionDispatchService: assignment refusé', [
                'assignment_id'    => $assignment->id,
                'reason'           => $reason,
                'response_seconds' => $responseSeconds,
            ]);

            // Escalade au suivant immédiatement
            return $this->dispatchToNextProvider($assignment->mission, $assignment->id);
        });
    }

    /**
     * Marque l'assignment comme expiré (timeout) et lance l'escalation.
     * Appelé par EscalateMissionAssignmentJob.
     */
    public function expireAndEscalate(MissionAssignment $assignment): ?MissionAssignment
    {
        return DB::transaction(function () use ($assignment) {
            $assignment->refresh();

            // Si déjà accepté ou refusé entretemps, ne rien faire
            if ($assignment->assignment_status !== 'assigned') {
                return null;
            }

            // Si pas vraiment expiré (job déclenché trop tôt), ne rien faire
            if ($assignment->expires_at && $assignment->expires_at->isFuture()) {
                return null;
            }

            $assignment->update([
                'assignment_status' => 'expired',
                'declined_at'       => now(),
                'decline_reason'    => 'Pas de réponse dans le délai imparti',
            ]);

            Log::info('MissionDispatchService: assignment expiré', [
                'assignment_id' => $assignment->id,
            ]);

            return $this->dispatchToNextProvider($assignment->mission, $assignment->id);
        });
    }

    protected function guardAcceptable(MissionAssignment $assignment): void
    {
        if ($assignment->assignment_status !== 'assigned') {
            throw new \DomainException(
                "Cette offre n'est plus acceptable (statut actuel: {$assignment->assignment_status})."
            );
        }

        if ($assignment->expires_at && $assignment->expires_at->isPast()) {
            throw new \DomainException("Cette offre a expiré.");
        }
    }

    protected function guardDeclinable(MissionAssignment $assignment): void
    {
        if ($assignment->assignment_status !== 'assigned') {
            throw new \DomainException(
                "Cette offre n'est plus refusable (statut actuel: {$assignment->assignment_status})."
            );
        }
    }
}
