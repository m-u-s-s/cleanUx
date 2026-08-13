<?php

namespace App\Services\Missions;

use App\Events\MissionStatusUpdated;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\EmployeEnRouteNotification;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionEndCodeNotification;
use App\Notifications\MissionPayoutAnnouncedNotification;
use App\Notifications\MissionStartedNotification;
use App\Services\Geo\OnSiteVerifier;
use App\Services\Missions\OnSite\MissionClosureService;
use App\Services\Notifications\SmsService;
use App\Services\Payments\CommissionService;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\PayoutAnnouncementService;
use App\Services\Payments\ProviderWalletService;
use App\Services\TripTracking\TripTrackingService;
use App\Services\Workforce\TimesheetService;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MissionLifecycleService
{
    public function __construct(
        protected MissionAssignmentStatusService $assignmentStatusService,
        protected MissionVerificationCodeService $verificationCodeService,
        protected MissionFromRendezVousSyncService $missionFromRendezVousSyncService,
        protected MissionTrackingService $missionTrackingService,
        protected MissionQualityService $missionQualityService,
    ) {}

    protected function assertRequiredChecklistCompleted(Mission $mission): void
    {
        $mission->loadMissing('checklists.items');

        $missingRequiredItems = $mission->checklists
            ->flatMap(fn ($checklist) => $checklist->items)
            ->filter(fn ($item) => $item->is_required && $item->status !== 'done');

        if ($missingRequiredItems->isNotEmpty()) {
            throw new RuntimeException(
                'Impossible de terminer la mission : certaines tâches obligatoires ne sont pas cochées.'
            );
        }
    }

    public function createFromRendezVous(Booking $rendezVous): Mission
    {
        return $this->missionFromRendezVousSyncService->createFromRendezVous($rendezVous);
    }

    public function setEnRoute(Mission $mission, User $user): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $mission->update([
            'status' => MissionStatus::EN_ROUTE,
        ]);

        event(new MissionStatusUpdated($mission));
        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'accepted', [
            'accepted_at' => now(),
        ]);

        $mission = $mission->fresh(['assignments', 'booking.client', 'leadEmployee']);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new EmployeEnRouteNotification($mission));
        }

        app(SmsService::class)->send(
            $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
            'Brio : votre employé est en route. Vous pouvez suivre sa position depuis votre espace client.'
        );

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_en_route',
            'Employé en route',
            'Le trajet vers le client a commencé.'
        );

        return $mission;
    }

    public function setArrived(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $this->missionTrackingService->stopActiveForMission($mission, $lat, $lng);

        $mission->update([
            'status' => MissionStatus::ARRIVED,
            'start_lat' => $lat,
            'start_lng' => $lng,
        ]);

        event(new MissionStatusUpdated($mission));

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'arrived_at' => now(),
        ]);

        $generated = $this->verificationCodeService->createVerificationCode($mission, 'start');
        session()->put('mission_start_code_'.$mission->id, $generated['code']);
        app(SmsService::class)->send(
            $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
            'Brio : votre employé est arrivé. Code de début : '.$generated['code']
        );

        $this->issueEndCode($mission);

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'booking.client', 'leadEmployee']);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(
                new EmployeArriveNotification($mission, $generated['code'])
            );
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_arrived',
            'Employé arrivé',
            'L’employé est arrivé sur place.'
        );

        return $mission;
    }

    public function generateStartCode(Mission $mission): array
    {
        return $this->verificationCodeService->createVerificationCode($mission, 'start');
    }

    public function validateStartCode(Mission $mission, User $user, string $plainCode, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $this->verificationCodeService->consumeValidCode($mission, 'start', $plainCode, $user);

        $mission->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now(),
            'started_by_user_id' => $user->id,
            'client_presence_confirmed' => true,
            'start_lat' => $lat ?? $mission->start_lat,
            'start_lng' => $lng ?? $mission->start_lng,
        ]);

        event(new MissionStatusUpdated($mission));

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'accepted_at' => now(),
        ]);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new MissionStartedNotification($mission));
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_started',
            'Mission démarrée',
            'La mission a démarré avec validation client.'
        );

        return $mission->fresh(['verificationCodes', 'assignments']);
    }

    public function generateEndCode(Mission $mission): array
    {
        if (! in_array($mission->status, MissionStatus::canFinish(), true)) {
            throw new RuntimeException('La mission doit être démarrée avant de générer un code de fin.');
        }

        return $this->issueEndCode($mission);
    }

    /**
     * ÉMET LE CODE DE FIN ET LE CONFIE À DES PORTEURS QUI ATTEIGNENT LE CLIENT.
     *
     * Passage obligé de toute création de code de fin, et la raison en tient au stockage :
     * `createVerificationCode()` n'enregistre qu'une empreinte. Les six chiffres n'existent que
     * dans la valeur de retour — qui les laisse échapper les oublie définitivement, sans qu'aucune
     * page ni aucun support ne puisse les retrouver ensuite.
     *
     * Le SMS seul ne suffisait pas. Il part vers un unique numéro, et le plafond du module SMS
     * (cinq messages par heure et par numéro) le fait basculer en `rate_limited` sans que rien ne
     * le signale : ni le prestataire, ni le client n'apprennent que le code s'est évaporé. La
     * notification, elle, atterrit dans le suivi web du client, qu'il peut consulter à volonté.
     *
     * L'échec d'un porteur ne doit pas empêcher l'autre : une arrivée déclarée ne peut pas être
     * annulée parce qu'un canal de messagerie est indisponible.
     *
     * @return array<string, mixed> Le code en clair et son enregistrement — cf. createVerificationCode().
     */
    public function issueEndCode(Mission $mission): array
    {
        $generated = $this->verificationCodeService->createVerificationCode($mission, 'end');

        $mission->loadMissing('booking.client');

        try {
            app(SmsService::class)->send(
                $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
                'Brio : code de fin de mission : '.$generated['code'].'. Communiquez-le au prestataire en fin de service.'
            );
        } catch (\Throwable $e) {
            Log::warning('SMS du code de fin non parti', [
                'mission_id' => $mission->id,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $mission->booking?->client?->notify(
                new MissionEndCodeNotification($mission, $generated['code'], $generated['record'])
            );
        } catch (\Throwable $e) {
            Log::warning('Notification du code de fin non partie', [
                'mission_id' => $mission->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $generated;
    }

    /**
     * Clôture la mission avec le code de fin affiché par le client.
     *
     * Le code atteste d'une POSSESSION, pas d'une présence : photographié puis transmis, ou dicté
     * au téléphone, il se valide depuis n'importe où. L'enjeu est plus lourd qu'au démarrage —
     * clôturer encaisse le paiement pré-autorisé — d'où le croisement avec la position.
     *
     * `$requirePosition` par défaut à `false`, à dessein et non par oubli : six chemins mènent ici
     * et tous n'ont pas de position à offrir. Une clôture depuis le tableau de bord web se fait
     * derrière un bureau ; l'exiger partout la rendrait impossible. Le scan mobile, lui, passe
     * `true` — c'est là qu'une position existe, et c'est là que la fraude est commode.
     *
     * Une position FOURNIE est en revanche toujours vérifiée, quel que soit l'appelant : on ne
     * peut pas en envoyer une lointaine et être accepté.
     *
     * Le contrôle précède la consommation du code : être au mauvais endroit n'est pas se tromper
     * de code, et brûler le code du client sur un problème de position obligerait à lui en faire
     * afficher un neuf pour rien.
     *
     * @throws ValidationException si la position contredit le lieu
     */
    public function validateEndCode(
        Mission $mission,
        User $user,
        string $plainCode,
        ?float $lat = null,
        ?float $lng = null,
        ?float $accuracyM = null,
        bool $mocked = false,
        bool $requirePosition = false,
    ): Mission {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $geo = $this->verifyOnSite($mission, $lat, $lng, $accuracyM, $mocked, $requirePosition);

        $record = $this->verificationCodeService->consumeValidCode($mission, 'end', $plainCode, $user);

        /*
         * UN CODE CORRECT NE DOIT PAS ÊTRE DÉTRUIT PAR UN REFUS QUI NE LE CONCERNE PAS.
         *
         * Le code était consommé PUIS la clôture tentée. Quand celle-ci est refusée pour une autre
         * raison — une tâche obligatoire non cochée, de loin le cas le plus courant — le code
         * partait avec elle. Le prestataire devait alors redemander un code au client à chaque
         * tentative, pour un motif étranger au code, et le client se demandait pourquoi il en
         * recevait sans arrêt.
         *
         * Constaté sur la base de démonstration : quatre codes de fin consommés, le dernier
         * VALIDÉ à 21:10, et une mission restée `started` avec six tâches ouvertes.
         *
         * `attempts` N'EST PAS REMIS À ZÉRO : le plafond anti-force-brute compte les saisies, et
         * une saisie a bien eu lieu. Seule la consommation est annulée, puisqu'elle atteste d'une
         * clôture qui n'a pas eu lieu.
         */
        try {
            return $this->completeMission($mission, $user, $lat, $lng, $geo);
        } catch (\Throwable $e) {
            $record->forceFill([
                'is_consumed' => false,
                'validated_at' => null,
                'validated_by_user_id' => null,
            ])->save();

            throw $e;
        }
    }

    /**
     * Confronte une position au lieu de l'intervention, ou lève.
     *
     * La politique est partagée avec la preuve de présence — même question, même réponse : la
     * clôture ne doit pas finir plus permissive que l'arrivée alors que c'est elle qui encaisse.
     *
     * @return array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}
     */
    protected function verifyOnSite(
        Mission $mission,
        ?float $lat,
        ?float $lng,
        ?float $accuracyM,
        bool $mocked,
        bool $requirePosition,
    ): array {
        $geo = app(OnSiteVerifier::class)->verify(
            $lat,
            $lng,
            $mission->destination_lat !== null ? (float) $mission->destination_lat : null,
            $mission->destination_lng !== null ? (float) $mission->destination_lng : null,
            $accuracyM,
            $mocked,
            $requirePosition,
        );

        if ($geo['failure'] !== null) {
            throw ValidationException::withMessages($geo['failure']);
        }

        return $geo;
    }

    public function validateStartCodeFromQr(Mission $mission, User $user, ?float $lat = null, ?float $lng = null): Mission
    {
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);

        $mission->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now(),
            'started_by_user_id' => $user->id,
            'client_presence_confirmed' => true,
            'start_lat' => $lat ?? $mission->start_lat,
            'start_lng' => $lng ?? $mission->start_lng,
        ]);

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'arrived', [
            'accepted_at' => now(),
        ]);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new MissionStartedNotification($mission));
        }

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_started_qr',
            'Mission démarrée via QR code',
            'La mission a démarré après scan du QR code client.'
        );

        return $mission->fresh(['verificationCodes', 'assignments']);
    }

    /**
     * @param  array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}|null  $geo
     *                                                                                                                  Verdict déjà rendu par l'appelant. Absent, il est calculé ici : toute clôture doit
     *                                                                                                                  porter le sien, sans quoi une colonne vide laisserait confondre « vérifié et proche »
     *                                                                                                                  avec « aucune position offerte ».
     */
    public function completeMission(
        Mission $mission,
        User $user,
        ?float $lat = null,
        ?float $lng = null,
        ?array $geo = null,
    ): Mission {
        $mission = app(MissionProfitService::class)
            ->calculate($mission);
        $this->assignmentStatusService->assertAssignedToMission($mission, $user);
        $this->assertRequiredChecklistCompleted($mission);

        // Clôturer sans code de fin reste possible — c'est un autre geste, pas celui du scan. La
        // position fournie est vérifiée quand même : personne ne doit pouvoir clôturer depuis
        // 40 km en annonçant où il se trouve.
        $geo ??= $this->verifyOnSite($mission, $lat, $lng, null, false, false);

        $mission->update([
            'status' => MissionStatus::COMPLETED,
            'actual_end_at' => now(),
            'closed_by_user_id' => $user->id,
            'end_lat' => $lat,
            'end_lng' => $lng,
            'end_distance_m' => $geo['distance_m'],
            'end_geo_verdict' => $geo['verdict'],
        ]);

        event(new MissionStatusUpdated($mission));

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'completed', [
            'completed_at' => now(),
        ]);

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'booking.client', 'leadEmployee']);
        if ($mission->booking) {
            app(MissionPaymentService::class)
                ->capture($mission->booking);
        }

        // Wire payout ledger: calculate commission + create ProviderPayout record after capture
        $mission = $mission->fresh(['assignments', 'booking', 'leadProvider']);
        if ($mission->booking && $mission->booking->payment_status === 'captured') {
            try {
                $commission = app(CommissionService::class)
                    ->calculateForBooking($mission->booking);

                // This branch only runs once the PaymentIntent is captured. Because
                // MissionPaymentService::authorize() always creates a destination charge
                // (transfer_data.destination), capturing it has ALREADY transferred the
                // provider's share to their Connect account. Mark the booking with the
                // explicit 'auto_transferred' status so the payouts:process Phase 2 manual
                // transfer never re-pays it (which would double-pay the provider). See A1.
                $updates = [
                    'payout_status' => 'auto_transferred',
                    'platform_fee_cents' => $commission['platform_fee_cents'],
                ];
                if (Schema::hasColumn('bookings', 'provider_payout_cents')) {
                    $updates['provider_payout_cents'] = $commission['provider_payout_cents'];
                } elseif (Schema::hasColumn('bookings', 'provider_amount_cents')) {
                    $updates['provider_amount_cents'] = $commission['provider_payout_cents'];
                }
                $mission->booking->forceFill($updates)->save();

                $providerId = $mission->lead_provider_user_id
                    ?? $mission->assignments()->where('assignment_status', 'accepted')->value('user_id');

                if ($providerId) {
                    ProviderPayout::create([
                        'provider_user_id' => $providerId,
                        'amount' => $commission['provider_payout_cents'] / 100,
                        'currency' => 'eur',
                        'status' => ProviderPayout::STATUS_PENDING,
                        'provider' => 'stripe_connect',
                        'period_start' => now()->toDateString(),
                        'period_end' => now()->toDateString(),
                        'metadata' => [
                            'mission_id' => $mission->id,
                            'booking_id' => $mission->booking->id,
                            'stripe_payment_intent_id' => $mission->booking->stripe_payment_intent_id,
                            'commission_rate' => $commission['commission_rate'],
                            'platform_fee_cents' => $commission['platform_fee_cents'],
                            'auto_transferred' => true,
                        ],
                    ]);

                    // Credit provider wallet ledger via ProviderWalletService::recordEarning.
                    // This is idempotent (deduplicates via idempotency_key = earning:booking:{id}:pi:{pi_id})
                    // so it is safe to call even when the payment_intent.succeeded webhook also
                    // calls recordEarning later — the second call becomes a no-op.
                    // Passing null for $intent causes recordEarning to fall back to
                    // $booking->stripe_payment_intent_id for the idempotency key, which is the
                    // same value the webhook handler will use, ensuring proper deduplication.
                    if ($mission->booking instanceof Booking) {
                        app(ProviderWalletService::class)->recordEarning($mission->booking);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[payout] Ledger creation failed (non-blocking)', [
                    'mission_id' => $mission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        /*
         * LA RÉSERVATION SUIT LA MISSION — sans quoi le client ne peut jamais noter.
         *
         * `RatingService::guardEligibility()` n'autorise un avis que sur une prestation TERMINÉE,
         * et il lit le statut de la RÉSERVATION, pas celui de la mission. Or clôturer une mission
         * ne touchait pas la réservation : elle restait `confirme` indéfiniment. Tout avis client
         * était donc refusé par « Vous ne pouvez noter qu'une prestation terminée » — y compris
         * depuis le lien « Donner mon avis » du courriel de fin de mission, qui menait droit à un
         * formulaire incapable d'aboutir.
         *
         * `mission_finished_at` démarre la fenêtre de notation de 30 jours que ce même garde
         * applique : la laisser vide rendait la fenêtre inopérante dans l'autre sens.
         *
         * `forceFill` plutôt que `update`, comme le bloc de paiement ci-dessus : la clôture ne doit
         * pas dépendre de la composition de `$fillable`, qui a déjà écarté des colonnes en silence.
         */
        if ($mission->booking) {
            $mission->booking->forceFill([
                'status' => BookingStatus::TERMINE,
                'mission_finished_at' => $mission->booking->mission_finished_at ?? now(),
                'completed_at' => $mission->booking->completed_at ?? now(),
            ])->save();

            $mission->setRelation('booking', $mission->booking->fresh());
        }

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new MissionCompletedNotification($mission));
        }
        app(SmsService::class)->send(
            $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
            'Brio : merci d’avoir fait confiance à Brio. Votre mission est terminée — laissez votre avis depuis votre espace client.'
        );

        /*
         * ET LE PRESTATAIRE, LUI, APPREND CE QU'IL A GAGNÉ.
         *
         * Tout ce qui précède part vers le client. Celui qui vient de travailler ne recevait rien :
         * ni confirmation que sa mission était enregistrée, ni montant, ni date. C'est pourtant le
         * seul instant où la question se pose et où la réponse est facile à donner.
         *
         * Soft-fail, comme le reste de cette clôture : une messagerie indisponible ne doit pas
         * empêcher une mission d'être terminée ni un paiement d'être capturé.
         */
        try {
            $annonceur = app(PayoutAnnouncementService::class);
            $beneficiaire = $annonceur->beneficiaire($mission) ?? $user;
            $annonce = $annonceur->pour($mission);

            if ($annonce) {
                $beneficiaire->notify(new MissionPayoutAnnouncedNotification($mission, $annonce));
            }
        } catch (\Throwable $e) {
            Log::warning('Annonce de gain non envoyée au prestataire', [
                'mission_id' => $mission->id,
                'message' => $e->getMessage(),
            ]);
        }

        $mission = $this->missionQualityService->refreshMissionQuality($mission->fresh());
        $this->missionQualityService->generateOrRefreshReport($mission, $user);

        app(MissionHistoryService::class)->log(
            $mission->fresh(),
            $user,
            'mission_completed',
            'Mission terminée',
            'La mission a été clôturée avec validation client.'
        );

        /*
         * LE RAPPORT EST PRODUIT, ARCHIVÉ ET ENVOYÉ (F9).
         *
         * Ce chemin ne faisait que générer un PDF sur un disque privé, que le client ne peut pas
         * atteindre. Un compte rendu que le destinataire ne reçoit pas est un fichier, pas un
         * compte rendu — et c'est précisément la pièce qu'on cherche trois semaines plus tard quand
         * une contestation arrive.
         *
         * `MissionClosureService` réunit les deux générateurs qui s'ignoraient — la fiche de
         * synthèse et le PDF — et prévient le client. Tout y est en soft-fail : une bibliothèque
         * PDF qui échoue ne doit pas empêcher un prestataire de terminer sa journée.
         */
        try {
            $rapport = app(MissionClosureService::class)->cloturer($mission, $user);

            $mission->update(['report_path' => $rapport->pdf_path]);
        } catch (\Throwable $e) {
            report($e);
        }

        /*
         * LE POINTAGE SE REMPLIT TOUT SEUL (E20).
         *
         * C'est la leçon de tous les systèmes de pointage : celui qui demande un geste de plus au
         * moment où l'on range son matériel n'est pas rempli, ou l'est de mémoire trois jours plus
         * tard — c'est-à-dire faux. La session de suivi sait déjà quand la personne est entrée en
         * mission, quand elle a fini, et combien de temps elle a été en pause.
         *
         * Soft-fail : une feuille d'heures manquante se rattrape, une clôture bloquée laisse le
         * prestataire sur le trottoir.
         */
        try {
            $session = app(TripTrackingService::class)->activeSessionForBooking((int) $mission->booking_id);

            if ($session) {
                app(TimesheetService::class)->pointerDepuisLeSuivi($mission, $session);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $mission->fresh(['assignments', 'verificationCodes']);
    }

    public function syncFromRendezVous(Booking $rendezVous): Mission
    {
        return $this->missionFromRendezVousSyncService->syncFromRendezVous($rendezVous);
    }
}
