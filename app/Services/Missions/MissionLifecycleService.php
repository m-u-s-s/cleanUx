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

        /*
         * « JE PARS CHEZ LE CLIENT » — LE MOMENT EXACT QUE LE MODULE PROTÈGE.
         *
         * Les trois surfaces (API mobile, session web, écran Livewire) convergent ici : c'est le
         * seul endroit où la garde couvre les trois d'un coup. Elle est posée APRÈS le contrôle
         * d'affectation, pour qu'un intervenant étranger à la mission reçoive toujours le refus
         * qui le concerne — et non un contrôle d'identité qui n'a pas lieu d'être.
         */
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

        /*
         * UNE COURSE N'ÉMET AUCUN CODE — et la branche est posée AVANT la génération, pas après.
         *
         * Les six chiffres attestent qu'un prestataire est bien face au client, devant sa porte.
         * Sur une course, la preuve est ailleurs : le client monte dans la voiture, et la trace GPS
         * de A à B dit le reste. Uber, Bolt et Heetch n'en demandent aucun, pour cette raison.
         *
         * Nettoyer après coup ne suffirait pas : `createVerificationCode()` envoie un SMS, et un SMS
         * parti ne se rattrape pas. Pire, le module plafonne à cinq messages par heure et par
         * numéro — deux codes inutiles consommeraient le quota du client sans que rien ne le
         * signale.
         */
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

            /*
             * LE CODE DE FIN N'EST PLUS ÉMIS ICI.
             *
             * Il l'était, dans la foulée du code de début : le client recevait les deux dans la
             * même minute, avant que le travail ait commencé. Un code de fin qu'on détient depuis
             * le début n'atteste plus rien de la fin — il ne prouve que sa propre existence. Et il
             * consommait un deuxième SMS sur un quota plafonné à cinq par heure et par numéro.
             *
             * Il est désormais émis quand il veut dire quelque chose : à la demande du
             * prestataire, mission démarrée, par le bouton « Générer code fin » qui existait déjà
             * et faisait double emploi. {@see self::generateEndCode()}
             *
             * L'affichage côté client attendait DÉJÀ le statut « démarrée » — c'est l'émission qui
             * était en avance sur ce que l'écran montrait.
             */
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

    /**
     * LA RÉSERVATION SUIT LA MISSION, à chaque étape et pas seulement à la fin.
     *
     * Rien n'écrivait `en_route` ni `sur_place` sur une réservation. Le client passait donc de
     * « confirmé » à « terminé » sans jamais voir que son prestataire était en route ou chez lui,
     * alors que c'est exactement ce qu'il attend de savoir. Trois conséquences, toutes silencieuses :
     *
     *   — `Booking::isInProgress()` n'était JAMAIS vrai, donc l'état client normalisé ne valait
     *     jamais `in_progress` et le bouton « Scanner QR — Terminer » de l'application cliente
     *     était inatteignable ;
     *   — les tableaux de bord employé, missions et planning comptent des réservations `en_route`
     *     et `sur_place` : ils affichaient zéro en permanence ;
     *   — `StatutRendezVousNotification` prévoit un message pour chacun de ces états, qui ne
     *     partait jamais.
     *
     * UNE RÉSERVATION TERMINÉE OU ANNULÉE N'EST PAS RÉVEILLÉE : une mission qui repasserait par ces
     * gestes après coup ne doit pas rouvrir ce que le client considère comme clos.
     */
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

        $geo = $this->verifyOnSite($mission, $lat, $lng, $accuracyM, $mocked, $requirePosition, $this->lieuDeCloture($mission));

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
     * Confronte une position AU LIEU OÙ LE GESTE EST CENSÉ AVOIR LIEU, ou lève.
     *
     * La politique est partagée avec la preuve de présence — même question, même réponse : la
     * clôture ne doit pas finir plus permissive que l'arrivée alors que c'est elle qui encaisse.
     *
     * CE LIEU N'EST PAS TOUJOURS CELUI DE L'INTERVENTION. Sur une intervention ordinaire, les deux
     * bouts se passent au même endroit et `mission.destination_*` répond à tout. Sur une COURSE, la
     * clôture a lieu au point de DÉPOSE, à des kilomètres du départ : comparer au lieu
     * d'intervention refuserait toute fin de course pour éloignement, avec le message « vous
     * semblez être à 8,3 km du lieu de l'intervention » — techniquement vrai, et complètement à
     * côté.
     *
     * D'où le paramètre explicite, plutôt qu'une seconde politique de proximité : deux copies de
     * cette règle dériveraient, et c'est la plus permissive qui finirait par décider.
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
     * Une intervention se termine là où elle a commencé ; une course se termine au point de dépose.
     * La règle vit ici, en un seul endroit, et sert autant la clôture par code que la clôture sans
     * code — deux copies finiraient par diverger, et l'une des deux refuserait des clôtures
     * légitimes pendant que l'autre les accepterait.
     *
     * @return array{0: float, 1: float}|null
     */
    /**
     * LE TEMPS RÉELLEMENT PASSÉ, ÉCRIT SUR LA RÉSERVATION.
     *
     * `bookings.duree_reelle` existe depuis mai et n'était écrite QUE par un employé tapant un
     * nombre à la main dans le rapport de fin web. Une mission clôturée depuis le mobile — donc
     * l'immense majorité — la laissait vide. Or elle est lue par `FinanceDocumentCalculator` pour
     * établir le coût interne et la marge : le calcul retombait sur la durée ESTIMÉE, et une
     * intervention qui déborde d'une heure paraissait aussi rentable qu'une autre.
     *
     * On n'écrase PAS une valeur déjà saisie : si un employé a corrigé la durée à la main, il a vu
     * quelque chose que l'horloge ignore — une pause non enregistrée, un départ anticipé.
     */
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

        /*
         * ÉCRITURE SANS ÉVÉNEMENT — pour le coût, désormais, et non plus pour la correction.
         *
         * Historiquement, un `$booking->save()` posé ici faisait retomber à `assigned` la mission
         * qu'on venait de clôturer : `RendezVousObserver::saved()` resynchronise sur toute
         * sauvegarde d'une réservation `confirme`, et la synchronisation réécrivait le statut à sa
         * valeur initiale. Cette cause est traitée à la racine dans
         * `MissionFromRendezVousSyncService::statutASynchroniser()`, qui ne rétrograde plus une
         * mission engagée.
         *
         * L'écriture directe reste néanmoins la bonne : une resynchronisation complète coûte un
         * géocodage, une reconstruction de checklist, un armement de SLA et une tentative
         * d'auto-assignation. Une durée mesurée n'a aucune raison de déclencher tout cela.
         */
        $booking->forceFill(['duree_reelle' => $minutes]);

        Booking::query()->whereKey($booking->getKey())->update(['duree_reelle' => $minutes]);
    }

    /**
     * Le couple `[latitude, longitude]` attendu à la clôture — la même forme que `verifyOnSite()`
     * consomme, et non un tableau nommé.
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
        /*
         * CLÔTURER DEUX FOIS NE REJOUE RIEN.
         *
         * Cette méthode n'avait aucune garde sur son propre état. Rappelée sur une mission déjà
         * terminée — un second appui, une reprise réseau, un écran resté ouvert — elle recapturait
         * le paiement, recréait la ligne de versement et renotifiait les deux parties.
         *
         * Constaté sur la mission #12 : UN seul événement `mission_completed`, mais TROIS annonces
         * de gain au prestataire, dont la dernière portait un montant différent des deux premières.
         * Trois promesses contradictoires pour un même travail, et rien pour départager.
         *
         * LE RETOUR EST SILENCIEUX, PAS UNE ERREUR : répéter une clôture qui a déjà abouti n'est
         * pas une faute, c'est le comportement normal d'un réseau qui bégaie. Ce qui serait fautif,
         * c'est d'en tirer un second mouvement d'argent.
         */
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

        /*
         * LA RENTABILITÉ SE CALCULE APRÈS LA CLÔTURE, jamais avant.
         *
         * Elle était appelée treize lignes plus haut, avant que `actual_end_at` ne soit écrit :
         * `MissionProfitService::calculateDuration()` rend 0 quand cette date est nulle, donc
         * `actual_duration_minutes` valait 0 sur TOUTE mission clôturée par le chemin normal.
         * `employee_cost` valait 0 dans la foulée, et `margin` égalait le prix client — une marge
         * de 100 % affichée à l'administrateur, et ce zéro renvoyé au mobile en fin de mission.
         *
         * C'est aussi la durée dont le dépassement a besoin : sans elle, aucune heure
         * supplémentaire ne pourrait être constatée.
         */
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

            /*
             * ON NE PROMET PAS UN VIREMENT PERSONNEL POUR UNE MISSION DE SOCIÉTÉ.
             *
             * Quand la mission appartient à une société prestataire, l'argent va à l'ENTREPRISE :
             * le chef d'équipe et les renforts sont salariés, ils ne touchent pas la course. Leur
             * annoncer « 150,80 € seront transférés sur votre compte » est faux, et c'est le genre
             * de faux qu'on ne découvre qu'au moment où quelqu'un attend son virement.
             *
             * Le cas a été trouvé en jouant le parcours complet pour les quatre couples de rôles :
             * il ne se voyait pas sur une étape isolée, seulement sur la jointure entre le montage
             * de la société et la clôture.
             *
             * Une annonce destinée à la SOCIÉTÉ reste à construire — elle suppose de désigner qui,
             * dans l'organisation, doit la recevoir. Se taire est provisoire mais juste ; promettre
             * à la mauvaise personne ne l'est pas.
             */
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
