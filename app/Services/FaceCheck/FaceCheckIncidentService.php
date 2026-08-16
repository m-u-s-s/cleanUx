<?php

namespace App\Services\FaceCheck;

use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\User;
use App\Notifications\FaceCheck\FaceCheckIncidentRaised;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * CE QUI APPELLE UN HUMAIN — et la gradation qui évite de l'appeler pour rien.
 *
 * ON N'ALERTE PAS AU PREMIER ABANDON. Un réseau qui lâche, une batterie vide, un appel entrant, un
 * tunnel : tous produisent exactement le même état qu'un prestataire qui ferme l'écran pour ne pas
 * se montrer. Alerter dès le premier ferait un canal d'alerte que plus personne ne lit au bout de
 * trois jours — et c'est ainsi qu'une vraie fraude passe inaperçue au milieu du bruit.
 *
 * La gradation est donc : rien, puis un incident `warning` au seuil, puis `critical` quand la
 * répétition ne s'explique plus par la malchance. Ce sont les seuils de l'administrateur, pas les
 * miens : ils vivent dans les réglages du module.
 */
class FaceCheckIncidentService
{
    public function __construct(
        private readonly FaceCheckSettings $settings,
    ) {}

    /**
     * Le prestataire signale que le contrôle ne marche pas.
     *
     * CE GESTE NE DÉBLOQUE RIEN, et c'est tout le point : un bouton « ça ne marche pas » qui
     * accorderait un sursis serait la porte de sortie que tout fraudeur emprunterait. Il ouvre un
     * dossier horodaté avec les diagnostics techniques, et un administrateur tranche.
     *
     * @param  array<string, mixed>  $diagnostics
     */
    public function reportByProvider(
        User $provider,
        string $message,
        array $diagnostics = [],
        ?ProviderFaceCheck $check = null,
    ): ProviderFaceIncident {
        $incident = ProviderFaceIncident::create([
            'user_id' => $provider->id,
            'provider_face_check_id' => $check?->id,
            'type' => ProviderFaceIncident::TYPE_PROVIDER_REPORT,
            'severity' => ProviderFaceIncident::SEVERITY_WARNING,
            'message' => $message,
            'diagnostics' => $diagnostics,
        ]);

        $this->journaliser('face_check.incident_reported', $incident, $provider);
        $this->notifierLesAdministrateurs($incident, $provider);

        return $incident;
    }

    /**
     * Un contrôle vient d'être abandonné. On compte, on ne réagit qu'au seuil.
     */
    public function noteAbandon(ProviderFaceCheck $check): ?ProviderFaceIncident
    {
        $provider = $check->user;

        if ($provider === null) {
            return null;
        }

        $abandons = ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $check->provider_face_profile_id)
            ->where('status', ProviderFaceCheck::STATUS_ABANDONED)
            ->where('requested_at', '>=', now()->subDays($this->settings->abandonWindowDays()))
            ->count();

        if ($abandons < $this->settings->abandonThreshold()) {
            return null;
        }

        $fraude = $abandons >= $this->settings->abandonFraudThreshold();

        return $this->ouvrirOuEscalader(
            provider: $provider,
            type: ProviderFaceIncident::TYPE_REPEATED_ABANDON,
            severity: $fraude ? ProviderFaceIncident::SEVERITY_CRITICAL : ProviderFaceIncident::SEVERITY_WARNING,
            message: $fraude
                ? "{$abandons} contrôles abandonnés en {$this->settings->abandonWindowDays()} jours : évitement probable."
                : "{$abandons} contrôles abandonnés en {$this->settings->abandonWindowDays()} jours.",
            occurrences: $abandons,
            check: $check,
        );
    }

    public function noteFailure(ProviderFaceCheck $check, int $echecsConsecutifs): ?ProviderFaceIncident
    {
        $provider = $check->user;

        if ($provider === null) {
            return null;
        }

        return $this->ouvrirOuEscalader(
            provider: $provider,
            type: ProviderFaceIncident::TYPE_REPEATED_FAILURE,
            severity: $echecsConsecutifs >= $this->settings->failureThreshold()
                ? ProviderFaceIncident::SEVERITY_CRITICAL
                : ProviderFaceIncident::SEVERITY_WARNING,
            message: "Contrôle facial échoué ({$echecsConsecutifs} d'affilée). Motif : ".($check->failure_reason ?? 'non précisé').'.',
            occurrences: $echecsConsecutifs,
            check: $check,
        );
    }

    /**
     * LA VIVACITÉ RATÉE EST TOUJOURS SIGNALÉE, dès la première fois.
     *
     * Elle ne se confond pas avec un mauvais éclairage : elle dit qu'on a présenté une image d'une
     * image. Il y a des faux positifs — un reflet, un écran derrière — d'où l'incident plutôt que
     * le blocage sec. Mais un humain doit le voir à chaque fois.
     */
    public function noteLivenessFailure(ProviderFaceCheck $check): ?ProviderFaceIncident
    {
        $provider = $check->user;

        if ($provider === null) {
            return null;
        }

        return $this->ouvrirOuEscalader(
            provider: $provider,
            type: ProviderFaceIncident::TYPE_LIVENESS_FAIL,
            severity: ProviderFaceIncident::SEVERITY_CRITICAL,
            message: "La détection de vivacité a échoué : l'image présentée pourrait être la photo d'une photo.",
            occurrences: 1,
            check: $check,
        );
    }

    public function noteIdMismatch(User $provider, ?float $score): ?ProviderFaceIncident
    {
        return $this->ouvrirOuEscalader(
            provider: $provider,
            type: ProviderFaceIncident::TYPE_ID_MISMATCH,
            severity: ProviderFaceIncident::SEVERITY_CRITICAL,
            message: "Le visage enrôlé ne correspond pas au portrait de la pièce d'identité"
                .($score !== null ? ' (similarité '.number_format($score, 1).' %).' : '.'),
            occurrences: 1,
            check: null,
        );
    }

    /**
     * Un appariement non concluant N'EST PAS un soupçon : c'est un scan de mauvaise qualité.
     * Il appelle un œil, pas une alerte.
     */
    public function noteIdInconclusive(User $provider, ?string $raison): ?ProviderFaceIncident
    {
        return $this->ouvrirOuEscalader(
            provider: $provider,
            type: ProviderFaceIncident::TYPE_ID_MISMATCH,
            severity: ProviderFaceIncident::SEVERITY_INFO,
            message: "L'appariement avec la pièce d'identité n'a pas pu conclure ("
                .($raison ?? 'motif non précisé').'). Une comparaison à l\'œil est nécessaire.',
            occurrences: 1,
            check: null,
        );
    }

    public function acknowledge(ProviderFaceIncident $incident, User $admin): ProviderFaceIncident
    {
        $incident->forceFill([
            'status' => ProviderFaceIncident::STATUS_ACKNOWLEDGED,
            'acknowledged_by_user_id' => $admin->id,
            'acknowledged_at' => now(),
        ])->save();

        $this->journaliser('face_check.incident_acknowledged', $incident, $incident->user);

        return $incident;
    }

    public function resolve(ProviderFaceIncident $incident, User $admin, string $resolution, ?string $note = null): ProviderFaceIncident
    {
        $incident->forceFill([
            'status' => $resolution === 'dismissed'
                ? ProviderFaceIncident::STATUS_DISMISSED
                : ProviderFaceIncident::STATUS_RESOLVED,
            'resolved_by_user_id' => $admin->id,
            'resolved_at' => now(),
            'resolution' => $resolution,
            'resolution_note' => $note,
        ])->save();

        $this->journaliser('face_check.incident_resolved', $incident, $incident->user);

        return $incident;
    }

    /**
     * UN INCIDENT OUVERT DU MÊME TYPE SE MET À JOUR, IL NE SE DUPLIQUE PAS.
     *
     * Sans cette règle, six abandons produiraient quatre incidents identiques, et l'administrateur
     * traiterait quatre fois le même dossier — ou n'en traiterait aucun.
     */
    private function ouvrirOuEscalader(
        User $provider,
        string $type,
        string $severity,
        string $message,
        int $occurrences,
        ?ProviderFaceCheck $check,
    ): ProviderFaceIncident {
        $existant = ProviderFaceIncident::query()
            ->where('user_id', $provider->id)
            ->where('type', $type)
            ->open()
            ->latest('id')
            ->first();

        if ($existant !== null) {
            $aggrave = $this->gravite($severity) > $this->gravite($existant->severity);

            $existant->forceFill([
                'severity' => $aggrave ? $severity : $existant->severity,
                'message' => $message,
                'occurrence_count' => max($occurrences, $existant->occurrence_count + 1),
                'provider_face_check_id' => $check !== null ? $check->id : $existant->provider_face_check_id,
            ])->save();

            $this->journaliser('face_check.incident_escalated', $existant, $provider);

            // On ne renotifie que si la situation s'est aggravée : sinon l'alerte devient du bruit.
            if ($aggrave) {
                $this->notifierLesAdministrateurs($existant, $provider);
            }

            return $existant;
        }

        $incident = ProviderFaceIncident::create([
            'user_id' => $provider->id,
            'provider_face_check_id' => $check?->id,
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'occurrence_count' => $occurrences,
        ]);

        $this->journaliser('face_check.incident_opened', $incident, $provider);
        $this->notifierLesAdministrateurs($incident, $provider);

        return $incident;
    }

    private function gravite(string $severity): int
    {
        return match ($severity) {
            ProviderFaceIncident::SEVERITY_CRITICAL => 3,
            ProviderFaceIncident::SEVERITY_WARNING => 2,
            default => 1,
        };
    }

    /**
     * Le même ciblage que `SafetyAlertService` : tous les administrateurs actifs, et une trace
     * dans les journaux s'il n'y en a aucun — une alerte sans destinataire est une alerte perdue.
     */
    private function notifierLesAdministrateurs(ProviderFaceIncident $incident, User $provider): void
    {
        try {
            $admins = User::query()
                ->whereIn('platform_role', ['admin', 'super_admin'])
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                Log::warning('[face_check] incident sans destinataire administrateur', [
                    'incident_id' => $incident->id,
                ]);

                return;
            }

            Notification::send($admins, new FaceCheckIncidentRaised($incident, $provider));
        } catch (\Throwable $e) {
            // L'écriture du dossier est faite : elle ne doit pas être perdue si l'e-mail tombe.
            report($e);
        }
    }

    private function journaliser(string $evenement, ProviderFaceIncident $incident, ?User $provider): void
    {
        try {
            ActivityLogger::log($evenement, $incident, [
                'type' => $incident->type,
                'severity' => $incident->severity,
                'provider_user_id' => $provider?->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
