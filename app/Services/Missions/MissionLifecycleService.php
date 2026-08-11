<?php

namespace App\Services\Missions;

use App\Services\Missions\OnSite\MissionClosureService;
use App\Events\MissionStatusUpdated;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\EmployeEnRouteNotification;
use App\Notifications\MissionCompletedNotification;
use App\Notifications\MissionStartedNotification;
use App\Services\Geo\OnSiteVerifier;
use App\Services\Notifications\SmsService;
use App\Services\Payments\CommissionService;
use App\Services\Payments\MissionPaymentService;
use App\Services\Payments\ProviderWalletService;
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

        $generatedEnd = $this->verificationCodeService->createVerificationCode($mission, 'end');
        app(SmsService::class)->send(
            $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
            'Brio : code de fin de mission : '.$generatedEnd['code'].'. Communiquez-le au prestataire en fin de service.'
        );

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

        return $this->verificationCodeService->createVerificationCode($mission, 'end');
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

        $this->verificationCodeService->consumeValidCode($mission, 'end', $plainCode, $user);

        return $this->completeMission($mission, $user, $lat, $lng, $geo);
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

        if ($mission->booking?->client) {
            $mission->booking->client->notify(new MissionCompletedNotification($mission));
        }
        app(SmsService::class)->send(
            $mission->booking?->client?->phone ?? $mission->booking?->telephone_client,
            'Brio : votre mission est terminée. Merci de laisser votre avis depuis votre espace client.'
        );

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

        return $mission->fresh(['assignments', 'verificationCodes']);
    }

    public function syncFromRendezVous(Booking $rendezVous): Mission
    {
        return $this->missionFromRendezVousSyncService->syncFromRendezVous($rendezVous);
    }
}
