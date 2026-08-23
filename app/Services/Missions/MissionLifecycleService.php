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
use App\Services\FaceCheck\Exceptions\FaceCheckRequiredException;
use App\Services\FaceCheck\FaceCheckGate;
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

        // « JE PARS CHEZ LE CLIENT » — LE MOMENT EXACT QUE LE MODULE PROTÈGE.
        $verdict = $mission->booking !== null
            ? app(FaceCheckGate::class)->inspectForBooking($user, $mission->booking)
            : app(FaceCheckGate::class)->inspectProvider($user);

        if (! $verdict->allowed()) {
            throw new FaceCheckRequiredException($verdict);
        }

        $mission->update([
            'status' => MissionStatus::EN_ROUTE,
        ]);

        event(new MissionStatusUpdated($mission));
        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'accepted', [
            'accepted_at' => now(),
        ]);

        $this->avancerLaReservation($mission, BookingStatus::EN_ROUTE);

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

        $this->avancerLaReservation($mission, BookingStatus::SUR_PLACE);

        // UNE COURSE N'ÉMET AUCUN CODE — et la branche est posée AVANT la génération, pas après.
        $estUneCourse = (bool) $mission->booking?->estUneCourse();
        $generated = null;
        // Le destinataire est résolu UNE fois : les deux branches écrivent au même client, et deux
        // expressions parallèles finiraient par diverger sur un repli.
        $destinataire = $mission->booking?->client?->phone ?? $mission->booking?->telephone_client;

        if (! $estUneCourse) {
            $generated = $this->verificationCodeService->createVerificationCode($mission, 'start');
            session()->put('mission_start_code_'.$mission->id, $generated['code']);
            app(SmsService::class)->send(
                $destinataire,
                'Brio : votre employé est arrivé. Code de début : '.$generated['code']
            );

            // LE CODE DE FIN N'EST PLUS ÉMIS ICI.
        } else {
            app(SmsService::class)->send(
                $destinataire,
                'Brio : votre chauffeur est arrivé au point de prise en charge.'
            );
        }

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'booking.client', 'leadEmployee']);

        if ($mission->booking?->client) {
            $mission->booking->client->notify(
                new EmployeArriveNotification($mission, $generated['code'] ?? null)
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

    /** LA RÉSERVATION SUIT LA MISSION, à chaque étape et pas seulement à la fin. */
    protected function avancerLaReservation(Mission $mission, string $statut): void
    {
        $reservation = $mission->booking;

        if (! $reservation) {
            return;
        }

        if (in_array($reservation->status, [BookingStatus::TERMINE, BookingStatus::ANNULE], true)) {
            return;
        }

        $reservation->forceFill(['status' => $statut])->save();
        $mission->setRelation('booking', $reservation->fresh());
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

        $geo = $this->verifyOnSite($mission, $lat, $lng, $accuracyM, $mocked, $requirePosition, $this->lieuDeCloture($mission));

        $record = $this->verificationCodeService->consumeValidCode($mission, 'end', $plainCode, $user);

        // UN CODE CORRECT NE DOIT PAS ÊTRE DÉTRUIT PAR UN REFUS QUI NE LE CONCERNE PAS.
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
     * Confronte une position AU LIEU OÙ LE GESTE EST CENSÉ AVOIR LIEU, ou lève.
     *
     * @param  array{0: float, 1: float}|null  $lieuAttendu  Le lieu du geste. `null` s'en remet à
     *                                                       `mission.destination_*`, qui est le
     *                                                       comportement historique et reste celui
     *                                                       de toutes les interventions ordinaires.
     * @return array{failure: array<string, list<string>>|null, verdict: string|null, distance_m: int|null}
     */
    protected function verifyOnSite(
        Mission $mission,
        ?float $lat,
        ?float $lng,
        ?float $accuracyM,
        bool $mocked,
        bool $requirePosition,
        ?array $lieuAttendu = null,
    ): array {
        $destLat = $lieuAttendu[0]
            ?? ($mission->destination_lat !== null ? (float) $mission->destination_lat : null);
        $destLng = $lieuAttendu[1]
            ?? ($mission->destination_lng !== null ? (float) $mission->destination_lng : null);

        $geo = app(OnSiteVerifier::class)->verify(
            $lat,
            $lng,
            $destLat,
            $destLng,
            $accuracyM,
            $mocked,
            $requirePosition,
        );

        if ($geo['failure'] !== null) {
            throw ValidationException::withMessages($geo['failure']);
        }

        return $geo;
    }

    /**
     * OÙ LA CLÔTURE EST CENSÉE AVOIR LIEU.
     *
     * @return array{0: float, 1: float}|null
     */
    /** LE TEMPS RÉELLEMENT PASSÉ, ÉCRIT SUR LA RÉSERVATION. */
    protected function reporterLaDureeReelle(Mission $mission): void
    {
        $booking = $mission->booking;

        if (! $booking || $booking->duree_reelle !== null) {
            return;
        }

        $debut = $mission->actual_start_at;
        $fin = $mission->actual_end_at;

        if ($debut === null || $fin === null) {
            return;
        }

        $minutes = (int) round(abs($debut->diffInSeconds($fin)) / 60);

        if ($minutes <= 0) {
            return;
        }

        // ÉCRITURE SANS ÉVÉNEMENT — pour le coût, désormais, et non plus pour la correction.
        $booking->forceFill(['duree_reelle' => $minutes]);

        Booking::query()->whereKey($booking->getKey())->update(['duree_reelle' => $minutes]);
    }

    /**
     * Le couple `[latitude, longitude]` attendu à la clôture — la même forme que `verifyOnSite()` consomme, et non un tableau nommé.
     *
     * @return array{float, float}|null
     */
    public function lieuDeCloture(Mission $mission): ?array
    {
        $reservation = $mission->booking;

        if (! $reservation?->estUneCourse()) {
            return null;
        }

        return [(float) $reservation->dropoff_lat, (float) $reservation->dropoff_lng];
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
        // CLÔTURER DEUX FOIS NE REJOUE RIEN.
        if ($mission->status === MissionStatus::COMPLETED) {
            return $mission->fresh(['assignments', 'verificationCodes']);
        }

        $this->assignmentStatusService->assertAssignedToMission($mission, $user);
        $this->assertRequiredChecklistCompleted($mission);

        // Clôturer sans code de fin reste possible — c'est un autre geste, pas celui du scan. La
        // position fournie est vérifiée quand même : personne ne doit pouvoir clôturer depuis
        // 40 km en annonçant où il se trouve. Sur une course, le lieu attendu est le point de
        // DÉPOSE : comparer au point de départ refuserait toute fin de course.
        $geo ??= $this->verifyOnSite($mission, $lat, $lng, null, false, false, $this->lieuDeCloture($mission));

        $mission->update([
            'status' => MissionStatus::COMPLETED,
            'actual_end_at' => now(),
            'closed_by_user_id' => $user->id,
            'end_lat' => $lat,
            'end_lng' => $lng,
            'end_distance_m' => $geo['distance_m'],
            'end_geo_verdict' => $geo['verdict'],
        ]);

        // LA RENTABILITÉ SE CALCULE APRÈS LA CLÔTURE, jamais avant.
        $mission = app(MissionProfitService::class)->calculate($mission->refresh());

        $this->reporterLaDureeReelle($mission);

        event(new MissionStatusUpdated($mission));

        $this->assignmentStatusService->updateAssignmentStatus($mission, $user, 'completed', [
            'completed_at' => now(),
        ]);

        $mission = $mission->fresh(['assignments', 'verificationCodes', 'booking.client', 'leadEmployee']);
        if ($mission->booking) {
            app(MissionPaymentService::class)
                ->capture($mission->booking);

            // LE TEMPS SUPPLÉMENTAIRE, APRÈS LA CAPTURE ET JAMAIS AVANT.
            try {
                app(HourlySettlementService::class)->regler($mission);
            } catch (\Throwable $e) {
                report($e);
            }
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
                        // La devise du VERSEMENT est celle de la reservation, et le calcul de
                        // commission la porte deja : la reecrire en dur ferait dire « euros » a un
                        // versement qui n'en est pas.
                        'currency' => $commission['currency'],
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

        // LA RÉSERVATION SUIT LA MISSION — sans quoi le client ne peut jamais noter.
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

        // ET LE PRESTATAIRE, LUI, APPREND CE QU'IL A GAGNÉ.
        try {
            $annonceur = app(PayoutAnnouncementService::class);
            $beneficiaire = $annonceur->beneficiaire($mission) ?? $user;
            $annonce = $annonceur->pour($mission);

            // ON NE PROMET PAS UN VIREMENT PERSONNEL POUR UNE MISSION DE SOCIÉTÉ.
            if ($annonce && $mission->provider_organization_id === null) {
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

        // LE RAPPORT EST PRODUIT, ARCHIVÉ ET ENVOYÉ (F9).
        try {
            $rapport = app(MissionClosureService::class)->cloturer($mission, $user);

            $mission->update(['report_path' => $rapport->pdf_path]);
        } catch (\Throwable $e) {
            report($e);
        }

        // LE POINTAGE SE REMPLIT TOUT SEUL (E20).
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
