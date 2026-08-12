<?php

namespace App\Services\Dispatch;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Safety\MaskedCallService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LE CYCLE DE VIE D'UNE OFFRE : accepter, refuser, expirer.
 *
 * CE QUI A CHANGÉ. Cette classe décidait aussi « à qui maintenant », avec sa propre liste de
 * candidats, pendant que la recherche par rayons en utilisait une autre. Deux annuaires, deux
 * ordres, et une même course qui pouvait sortir par les deux chemins. Le choix du destinataire
 * appartient désormais à `DispatchEngine`, qui porte les vagues, l'échéance globale et la dernière
 * vague en broadcast.
 *
 * CE QUI RESTE ICI est le cycle de vie d'UNE offre, et notamment le verrou pessimiste de
 * l'acceptation : deux prestataires peuvent accepter à la même seconde après la dernière vague, et
 * un seul doit gagner. Sans ce verrou, deux personnes partent pour la même intervention et une
 * seule est payée.
 *
 * La classe reste le point d'entrée des contrôleurs et des écrans : la déplacer aurait cassé
 * l'application mobile, l'écran web et le forçage administrateur pour un gain de nommage.
 */
class MissionDispatchService
{
    /**
     * Le défaut historique, gardé pour les appelants qui le référencent.
     *
     * La valeur qui fait foi est `config('dispatch.default_timeout')` : vingt secondes, et
     * surchargeable par métier.
     */
    public const RESPONSE_TIMEOUT_SECONDS = 20;

    /**
     * Resolve the timeout in seconds for a given trade, falling back to the
     * platform default defined in config/dispatch.php.
     */
    public function resolveTimeoutForMission(Mission $mission): int
    {
        $booking = $mission->booking;

        return app(DispatchEngine::class)->immediateTimeout($booking);
    }

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
        /*
         * DÉLÉGATION AU MOTEUR — cette méthode ne décide plus « à qui maintenant ».
         *
         * Elle le décidait avec sa propre liste (`AiDispatchService::rankEmployees()`), pendant que
         * la recherche par rayons en utilisait une autre. Deux annuaires, deux ordres, et une même
         * course qui pouvait sortir par les deux chemins. Le moteur porte les vagues, l'échéance
         * globale et la dernière vague en broadcast ; les laisser ici en dupliquerait la moitié.
         *
         * La signature est CONSERVÉE : les commandes planifiées, le forçage administrateur et le
         * job d'escalade l'appellent toujours.
         */
        return app(DispatchEngine::class)->next($mission);
    }

    /**
     * Crée une offre pour un prestataire PRÉCIS — forçage administrateur, prestataire préféré.
     *
     * UNE SEULE FABRIQUE D'OFFRES. La création vit dans `DispatchEngine` : c'est elle qui écrit la
     * ligne, qui la transmet sur les trois canaux et qui arme le compte à rebours serveur. Deux
     * fabriques donnaient deux offres différentes selon le chemin — l'une notifiée en temps réel,
     * l'autre non, et un prestataire qui ne voyait jamais la modale quand un administrateur la lui
     * envoyait.
     *
     * @throws \DomainException si la vérification d'identité du prestataire n'est pas validée
     */
    public function createOffer(
        Mission $mission,
        User $provider,
        ?int $previousAssignmentId = null,
    ): MissionAssignment {
        // KYC = blocage strict, et il est LEVÉ ici plutôt que rendu `null` : ce chemin est celui
        // d'un humain qui désigne quelqu'un, et il doit savoir pourquoi son geste est refusé.
        if (! $provider->hasClearedKyc()) {
            throw new \DomainException(
                "Ce prestataire ne peut pas recevoir de mission : sa vérification d'identité (KYC) n'est pas validée."
            );
        }

        $assignment = app(DispatchEngine::class)->createOffer(
            $mission,
            $provider,
            $this->resolveTimeoutForMission($mission),
        );

        if (! $assignment) {
            throw new \DomainException('Cette offre n’a pas pu être créée.');
        }

        if ($previousAssignmentId !== null) {
            $assignment->update(['escalated_from_assignment_id' => $previousAssignmentId]);
        }

        return $assignment->fresh();
    }

    /**
     * Le prestataire accepte l'offre. Marque l'assignment, marque la mission
     * comme "assigned", annule les autres offres en cours pour cette mission.
     *
     * Race condition : si 2 prestataires acceptent dans la même seconde
     * (broadcast pattern, double-tap, etc.), on doit garantir qu'UN SEUL
     * voit son accept réussir. Solution : `lockForUpdate()` sur la mission
     * dans la transaction. Le second arrivé verra que la mission est déjà
     * `assigned` et lèvera DomainException via guardAcceptable().
     */
    public function accept(MissionAssignment $assignment): MissionAssignment
    {
        return DB::transaction(function () use ($assignment) {
            // Lock pessimiste sur la mission entière. Toute autre transaction
            // qui essaie de lire/écrire cette mission attendra le commit.
            // L'absence de ce lock laissait passer 2 accepts en parallèle
            // (les 2 update lisent la mission `planned`, les 2 la passent
            // en `assigned`, le second écrase le lead du premier).
            if ($assignment->mission_id) {
                Mission::query()
                    ->whereKey($assignment->mission_id)
                    ->lockForUpdate()
                    ->first();
            }

            $assignment->refresh();

            $this->guardAcceptable($assignment);

            $now = now();
            $responseSeconds = $assignment->notification_sent_at
                ? max(0, (int) $now->diffInSeconds($assignment->notification_sent_at))
                : null;

            $assignment->update([
                'assignment_status' => 'accepted',
                'accepted_at' => $now,
                'response_seconds' => $responseSeconds,
            ]);

            // Mission : passe à "assigned" si elle ne l'est pas déjà
            $mission = $assignment->mission;
            if ($mission && $mission->status === 'planned') {
                // Écriture sur les DEUX colonnes lead pour compat :
                //  - lead_provider_user_id : utilisée par Phase 11+ (controllers,
                //    cancel, lifecycle, payouts) — désormais dans `$fillable`.
                //  - lead_employee_id : utilisée par channels.php (broadcast),
                //    par les vues admin/employé historiques, et par les
                //    relations Eloquent existantes (leadEmployee).
                // Sans cette double-écriture, le prestataire ne voit pas sa
                // mission dans son inbox active (Phase 12) ET ne reçoit pas
                // les broadcasts Reverb (cassé côté client temps-réel).
                // SP1 Task 5 : propage la SOCIÉTÉ prestataire jusqu'à la mission.
                // L'org est dérivée du profil du worker assigné (null pour un
                // indépendant). Permet aux écrans/paiements org-scoped de
                // retrouver l'entreprise responsable de la mission.
                $providerOrgId = ProviderProfile::query()
                    ->where('user_id', $assignment->user_id)
                    ->value('organization_account_id');

                $mission->update([
                    'status' => 'assigned',
                    'lead_provider_user_id' => $assignment->user_id,
                    'lead_employee_id' => $assignment->user_id,
                    'provider_organization_id' => $providerOrgId,
                ]);

                // Synchronise le booking avec l'offre acceptée. Indispensable pour
                // le flow ASAP qui n'utilise plus QUE l'offre/escalade (plus de
                // confirmation directe) : sans ça le client verrait sa réservation
                // "en attente" alors que la mission est assignée. status=confirmé
                // uniquement pour ASAP (le planifié garde son statut).
                $booking = $mission->booking;
                if ($booking) {
                    $bookingUpdates = [
                        'employe_id' => $assignment->user_id,
                        'matched_at' => $now,
                    ];
                    if (($booking->booking_mode ?? null) === 'asap') {
                        $bookingUpdates['status'] = 'confirme';
                    }
                    $booking->update($bookingUpdates);
                }
            }

            // Annuler les autres assignments en cours pour cette mission (au cas où)
            MissionAssignment::where('mission_id', $assignment->mission_id)
                ->where('id', '!=', $assignment->id)
                ->where('assignment_status', 'assigned')
                ->update([
                    'assignment_status' => 'cancelled',
                    'declined_at' => $now,
                    'decline_reason' => 'Autre prestataire a accepté en premier',
                ]);

            Log::info('MissionDispatchService: assignment accepté', [
                'assignment_id' => $assignment->id,
                'response_seconds' => $responseSeconds,
            ]);

            /*
             * LES AUTRES MODALES SE FERMENT, ET LA RECHERCHE AUSSI.
             *
             * Après la dernière vague, plusieurs prestataires ont la même course à l'écran. Sans ce
             * geste, leur compte à rebours continue sur une course déjà partie : ils appuient sur
             * « Accepter », reçoivent une erreur, et apprennent à ne plus croire les offres.
             */
            app(DispatchEngine::class)->onAccepted($assignment);

            $this->ouvrirLaLigneMasquee($assignment);

            return $assignment->fresh();
        });
    }

    /**
     * OUVRIR LA LIGNE MASQUÉE ENTRE LE CLIENT ET LE PRESTATAIRE (F8).
     *
     * `MaskedCallService` existait, complet, avec sa configuration et son pilote de test — et
     * n'était appelé de NULLE PART. Résultat : les deux parties échangeaient leurs vrais numéros
     * dès qu'il fallait se parler, ou ne se parlaient pas. Le premier cas laisse au prestataire le
     * téléphone personnel d'une cliente chez qui il est allé une fois ; le second fait sonner à la
     * porte sans réponse.
     *
     * L'ACCEPTATION EST LE BON MOMENT : avant, on ne sait pas qui viendra ; après, il est trop tard
     * pour le prestataire qui cherche l'entrée de l'immeuble.
     *
     * SOFT-FAIL ASSUMÉ. Un numéro manquant, une configuration absente, un service indisponible :
     * rien de cela ne justifie de faire échouer une acceptation de mission. L'acceptation est ce
     * qui compte pour les deux parties ; la ligne masquée est un confort qu'on réessaiera.
     */
    protected function ouvrirLaLigneMasquee(MissionAssignment $assignment): void
    {
        try {
            $mission = $assignment->mission;
            $booking = $mission?->booking;
            $client = $booking?->client;
            $prestataire = $assignment->user;

            if (! $booking || ! $client || ! $prestataire) {
                return;
            }

            app(MaskedCallService::class)->openSession($client, $prestataire, $booking);
        } catch (\Throwable $e) {
            Log::info('Ligne masquée non ouverte', [
                'assignment_id' => $assignment->id,
                'message' => $e->getMessage(),
            ]);
        }
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
                'declined_at' => $now,
                'decline_reason' => $reason,
                'response_seconds' => $responseSeconds,
            ]);

            Log::info('MissionDispatchService: assignment refusé', [
                'assignment_id' => $assignment->id,
                'reason' => $reason,
                'response_seconds' => $responseSeconds,
            ]);

            // La modale se ferme chez celui qui vient de refuser : sur mobile, la socket est ce qui
            // la fait disparaître, et sans ce message elle resterait jusqu'à l'expiration.
            app(DispatchEngine::class)->withdraw($assignment, 'declined');

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
                'declined_at' => now(),
                'decline_reason' => 'Pas de réponse dans le délai imparti',
            ]);

            Log::info('MissionDispatchService: assignment expiré', [
                'assignment_id' => $assignment->id,
            ]);

            // Le serveur a tranché : la modale doit se fermer, même si le téléphone comptait encore.
            app(DispatchEngine::class)->withdraw($assignment, 'expired');

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
            throw new \DomainException('Cette offre a expiré.');
        }

        // KYC = blocage strict : si la vérification a été révoquée/expirée entre
        // l'offre et l'acceptation, le prestataire ne peut pas accepter la mission.
        if (! optional($assignment->user)->hasClearedKyc()) {
            throw new \DomainException(
                "Acceptation impossible : votre vérification d'identité (KYC) n'est pas (plus) validée."
            );
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
