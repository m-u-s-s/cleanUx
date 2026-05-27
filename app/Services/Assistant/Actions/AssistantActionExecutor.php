<?php

namespace App\Services\Assistant\Actions;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Models\LoyaltyAccount;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\ProviderPresence;
use App\Models\User;

/**
 * Executes assistant actions (read-only and write-with-confirmation).
 *
 * Read actions return formatted French text strings.
 * Write actions that require confirmation return an array payload via executeWrite().
 * Confirmed write actions are executed via confirmAction().
 */
class AssistantActionExecutor
{
    private const WEEKDAYS_FR = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ];

    /** Write actions that require a confirmation round-trip before execution. */
    private const WRITE_ACTIONS = [
        'create_booking',
        'cancel_booking',
        'resolve_dispute',
    ];

    /**
     * Dispatch a read-only action by name and return its formatted French text.
     */
    public function execute(string $actionName, User $user, array $params = []): string
    {
        return match ($actionName) {
            'list_bookings'      => $this->listBookings($user),
            'booking_detail'     => $this->bookingDetail($user, $params['booking_id'] ?? null),
            'loyalty_balance'    => $this->loyaltyBalance($user),
            'next_mission'       => $this->nextMission($user),
            'earnings_summary'   => $this->earningsSummary($user),
            'availability_slots' => $this->availabilitySlots($user),
            'platform_kpis'      => $this->platformKpis(),
            'trade_stats'        => $this->tradeStats(),
            default              => "Action inconnue : {$actionName}",
        };
    }

    /**
     * Prepare a write action: persist a pending AssistantAction record and
     * return a confirmation payload the assistant can show to the user.
     *
     * @return array{action: string, requires_confirmation: bool, summary: string, params: array, action_id: int}
     */
    public function executeWrite(
        string $actionName,
        User $user,
        array $params = [],
        ?AssistantConversation $conversation = null,
    ): array {
        $summary = $this->buildConfirmationSummary($actionName, $user, $params);

        $record = AssistantAction::create([
            'assistant_conversation_id' => $conversation?->id,
            'user_id'                   => $user->id,
            'action_type'               => $actionName,
            'status'                    => AssistantAction::STATUS_PENDING_CONFIRMATION,
            'payload'                   => $params,
        ]);

        return [
            'action'                => $actionName,
            'requires_confirmation' => true,
            'summary'               => $summary,
            'params'                => $params,
            'action_id'             => $record->id,
        ];
    }

    /**
     * Confirm and execute a previously prepared write action.
     * Returns a human-readable French result string.
     */
    public function confirmAction(int $actionId, User $user): string
    {
        $action = AssistantAction::query()
            ->where('id', $actionId)
            ->where('user_id', $user->id)
            ->where('status', AssistantAction::STATUS_PENDING_CONFIRMATION)
            ->first();

        if (! $action) {
            return "Action introuvable, expirée ou déjà exécutée.";
        }

        $action->markConfirmed();

        $result = $this->dispatchWrite($action->action_type, $user, $action->payload ?? []);

        if (str_starts_with($result, 'Erreur')) {
            $action->markFailed($result);
        } else {
            $action->markExecuted(['result_text' => $result]);
        }

        return $result;
    }

    /**
     * Returns whether an action name is a write action requiring confirmation.
     */
    public function isWriteAction(string $actionName): bool
    {
        return in_array($actionName, self::WRITE_ACTIONS, true);
    }

    /**
     * Execute a low-risk write action immediately (no confirmation round-trip).
     * Currently handles: update_availability.
     */
    public function executeImmediateWrite(string $actionName, User $user, array $params = []): string
    {
        return $this->dispatchWrite($actionName, $user, $params);
    }

    // ──────────────────────────────────────────────────────
    // Write action dispatcher
    // ──────────────────────────────────────────────────────

    private function dispatchWrite(string $actionName, User $user, array $params): string
    {
        return match ($actionName) {
            'create_booking'    => $this->doCreateBooking($user, $params),
            'cancel_booking'    => $this->doCancelBooking($user, $params),
            'resolve_dispute'   => $this->doResolveDispute($user, $params),
            'update_availability' => $this->doUpdateAvailability($user, $params),
            default             => "Action d'écriture inconnue : {$actionName}",
        };
    }

    private function buildConfirmationSummary(string $actionName, User $user, array $params): string
    {
        return match ($actionName) {
            'create_booking'  => $this->summaryCreateBooking($params),
            'cancel_booking'  => $this->summaryCancelBooking($user, $params),
            'resolve_dispute' => $this->summaryResolveDispute($params),
            default           => "Confirmer l'action : {$actionName} ?",
        };
    }

    // ──────────────────────────────────────────────────────
    // Write action implementations
    // ──────────────────────────────────────────────────────

    private function doCreateBooking(User $user, array $params): string
    {
        $required = ['service_catalog_id', 'address', 'city', 'postal_code', 'scheduled_date', 'scheduled_time'];
        foreach ($required as $key) {
            if (empty($params[$key])) {
                return "Erreur : paramètre manquant « {$key} » pour créer la réservation.";
            }
        }

        $booking = Booking::create([
            'customer_user_id'   => $user->id,
            'service_catalog_id' => (int) $params['service_catalog_id'],
            'address'            => $params['address'],
            'city'               => $params['city'],
            'postal_code'        => $params['postal_code'],
            'scheduled_date'     => $params['scheduled_date'],
            'scheduled_time'     => $params['scheduled_time'],
            'status'             => 'en_attente',
            'booking_reference'  => 'CUX-' . strtoupper(substr(uniqid(), -6)),
        ]);

        $ref = $booking->booking_reference;
        return "Réservation {$ref} créée avec succès pour le {$params['scheduled_date']} à {$params['scheduled_time']} à {$params['city']}.";
    }

    private function doCancelBooking(User $user, array $params): string
    {
        $bookingId = $params['booking_id'] ?? null;

        if (! $bookingId) {
            return "Erreur : identifiant de réservation manquant.";
        }

        $booking = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            })
            ->where(function ($q) use ($bookingId) {
                $q->where('id', (int) $bookingId)
                  ->orWhere('booking_reference', $bookingId);
            })
            ->whereNotIn('status', ['annule', 'cancelled', 'completed', 'termine'])
            ->first();

        if (! $booking) {
            return "Réservation introuvable, déjà annulée, ou vous n'y avez pas accès.";
        }

        $booking->update(['status' => 'annule']);
        $ref = $booking->booking_reference ?? "#{$booking->id}";

        return "Réservation {$ref} annulée avec succès.";
    }

    private function doResolveDispute(User $user, array $params): string
    {
        $disputeId  = $params['dispute_id'] ?? null;
        $resolution = $params['resolution'] ?? null;

        if (! $disputeId || ! $resolution) {
            return "Erreur : identifiant du litige et texte de résolution requis.";
        }

        $dispute = ComplaintCase::query()
            ->whereNotIn('status', [ComplaintCase::STATUS_RESOLVED, ComplaintCase::STATUS_CLOSED])
            ->find((int) $disputeId);

        if (! $dispute) {
            return "Litige #{$disputeId} introuvable ou déjà résolu.";
        }

        DisputeResolution::create([
            'complaint_case_id' => $dispute->id,
            'resolution_type'   => DisputeResolution::TYPE_OTHER,
            'explanation'       => $resolution,
            'issued_by_user_id' => $user->id,
            'status'            => DisputeResolution::STATUS_APPLIED,
            'applied_at'        => now(),
        ]);

        $dispute->update([
            'status'      => ComplaintCase::STATUS_RESOLVED,
            'resolved_at' => now(),
            'admin_response' => $resolution,
        ]);

        return "Litige #{$disputeId} ({$dispute->reference}) résolu avec succès.";
    }

    /**
     * update_availability is low-risk: no confirmation required, called via dispatchWrite directly.
     */
    private function doUpdateAvailability(User $user, array $params): string
    {
        $requestedStatus = $params['status'] ?? null;
        $statusMap = [
            'online'  => ProviderPresence::STATUS_ONLINE,
            'offline' => ProviderPresence::STATUS_OFFLINE,
            'break'   => ProviderPresence::STATUS_ON_BREAK,
        ];

        if (! $requestedStatus || ! isset($statusMap[$requestedStatus])) {
            return "Statut invalide. Valeurs acceptées : online, offline, break.";
        }

        $mappedStatus = $statusMap[$requestedStatus];

        ProviderPresence::updateOrCreate(
            ['provider_user_id' => $user->id],
            ['status' => $mappedStatus, 'last_status_change_at' => now(), 'heartbeat_at' => now()]
        );

        $labels = ['online' => 'en ligne', 'offline' => 'hors ligne', 'break' => 'en pause'];
        return "Votre statut a été mis à jour : vous êtes maintenant {$labels[$requestedStatus]}.";
    }

    // ──────────────────────────────────────────────────────
    // Confirmation summary helpers
    // ──────────────────────────────────────────────────────

    private function summaryCreateBooking(array $params): string
    {
        $date = $params['scheduled_date'] ?? '—';
        $time = $params['scheduled_time'] ?? '—';
        $city = $params['city'] ?? '—';
        return "Créer une réservation le {$date} à {$time} à {$city} ?";
    }

    private function summaryCancelBooking(User $user, array $params): string
    {
        $bookingId = $params['booking_id'] ?? null;
        if (! $bookingId) {
            return "Annuler cette réservation ?";
        }

        $booking = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            })
            ->where(function ($q) use ($bookingId) {
                $q->where('id', (int) $bookingId)
                  ->orWhere('booking_reference', $bookingId);
            })
            ->first(['booking_reference', 'scheduled_date', 'scheduled_time']);

        if (! $booking) {
            return "Annuler la réservation #{$bookingId} ?";
        }

        $ref  = $booking->booking_reference ?? "#{$bookingId}";
        $date = $booking->scheduled_date ?? '—';
        $time = $booking->scheduled_time ? substr((string) $booking->scheduled_time, 0, 5) : '';
        return "Annuler la réservation {$ref} du {$date}" . ($time ? " à {$time}" : '') . " ?";
    }

    private function summaryResolveDispute(array $params): string
    {
        $disputeId  = $params['dispute_id'] ?? '—';
        $resolution = mb_strimwidth($params['resolution'] ?? '', 0, 80, '…');
        return "Résoudre le litige #{$disputeId} avec la résolution : « {$resolution} » ?";
    }

    // ──────────────────────────────────────────────────────
    // Client / Enterprise actions
    // ──────────────────────────────────────────────────────

    private function listBookings(User $user): string
    {
        $bookings = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            })
            ->latest()
            ->take(5)
            ->get(['id', 'booking_reference', 'status', 'scheduled_date', 'scheduled_time', 'address', 'city']);

        if ($bookings->isEmpty()) {
            return "Vous n'avez aucune réservation enregistrée.";
        }

        $lines = $bookings->map(function (Booking $b) {
            $ref  = $b->booking_reference ?? "#{$b->id}";
            $date = $b->scheduled_date ?? '—';
            $time = $b->scheduled_time ? substr((string) $b->scheduled_time, 0, 5) : '';
            $loc  = trim(($b->city ?? ''));
            $loc  = $loc ? " — {$loc}" : '';
            return "- {$ref} | {$b->status} | {$date} {$time}{$loc}";
        });

        return "Vos 5 dernières réservations :\n" . $lines->implode("\n");
    }

    private function bookingDetail(User $user, mixed $bookingId): string
    {
        if (! $bookingId) {
            return "Merci de préciser l'identifiant ou la référence de la réservation.";
        }

        $booking = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            })
            ->where(function ($q) use ($bookingId) {
                $q->where('id', (int) $bookingId)
                  ->orWhere('booking_reference', $bookingId);
            })
            ->first();

        if (! $booking) {
            return "Réservation introuvable ou vous n'y avez pas accès.";
        }

        $ref      = $booking->booking_reference ?? "#{$booking->id}";
        $date     = $booking->scheduled_date ?? '—';
        $time     = $booking->scheduled_time ? substr((string) $booking->scheduled_time, 0, 5) : '—';
        $address  = trim(implode(', ', array_filter([$booking->address, $booking->city])));
        $price    = $booking->estimated_price ? number_format((float) $booking->estimated_price, 2, ',', ' ') . ' €' : '—';

        return implode("\n", [
            "Réservation {$ref} :",
            "- Statut : {$booking->status}",
            "- Date/heure : {$date} à {$time}",
            "- Adresse : {$address}",
            "- Prix estimé : {$price}",
            "- Paiement : " . ($booking->payment_status ?? '—'),
        ]);
    }

    private function loyaltyBalance(User $user): string
    {
        $account = LoyaltyAccount::query()
            ->with('currentTier')
            ->where('user_id', $user->id)
            ->first();

        if (! $account) {
            return "Vous n'avez pas encore de compte fidélité actif.";
        }

        $tier = $account->currentTier?->name ?? 'Bronze';

        return implode("\n", [
            "Votre compte fidélité :",
            "- Tier : {$tier}",
            "- Points disponibles : {$account->redeemable_points} pts",
            "- Points période : {$account->period_points} pts",
            "- Points à vie : {$account->lifetime_points} pts",
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Provider / Employee actions
    // ──────────────────────────────────────────────────────

    private function nextMission(User $user): string
    {
        $mission = Mission::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id))
            ->whereIn('status', ['pending', 'confirmed', 'assigned', 'scheduled'])
            ->where('planned_start_at', '>=', now())
            ->orderBy('planned_start_at')
            ->first();

        if (! $mission) {
            return "Vous n'avez aucune mission prévue prochainement.";
        }

        $start   = $mission->planned_start_at?->format('d/m/Y à H:i') ?? '—';
        $address = $mission->rendezVous?->address ?? '—';

        return implode("\n", [
            "Prochaine mission :",
            "- Début prévu : {$start}",
            "- Adresse : {$address}",
            "- Statut : {$mission->status}",
        ]);
    }

    private function earningsSummary(User $user): string
    {
        $weekStart  = now()->startOfWeek();
        $monthStart = now()->startOfMonth();

        $thisWeek = (float) ProviderPayout::query()
            ->where('provider_user_id', $user->id)
            ->where('status', ProviderPayout::STATUS_PAID)
            ->where('period_end', '>=', $weekStart)
            ->sum('amount');

        $thisMonth = (float) ProviderPayout::query()
            ->where('provider_user_id', $user->id)
            ->where('status', ProviderPayout::STATUS_PAID)
            ->where('period_end', '>=', $monthStart)
            ->sum('amount');

        $pending = (float) ProviderPayout::query()
            ->where('provider_user_id', $user->id)
            ->whereIn('status', [ProviderPayout::STATUS_PENDING, ProviderPayout::STATUS_PROCESSING])
            ->sum('amount');

        return implode("\n", [
            "Résumé de vos revenus :",
            "- Cette semaine : " . number_format($thisWeek, 2, ',', ' ') . " €",
            "- Ce mois : " . number_format($thisMonth, 2, ',', ' ') . " €",
            "- En attente de virement : " . number_format($pending, 2, ',', ' ') . " €",
        ]);
    }

    private function availabilitySlots(User $user): string
    {
        $slots = AvailabilitySlot::query()
            ->forProvider($user->id)
            ->active()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get(['weekday', 'start_time', 'end_time']);

        if ($slots->isEmpty()) {
            return "Vous n'avez aucun créneau de disponibilité configuré.";
        }

        $lines = $slots->map(function (AvailabilitySlot $s) {
            $day   = self::WEEKDAYS_FR[$s->weekday] ?? "Jour {$s->weekday}";
            $start = substr((string) $s->start_time, 0, 5);
            $end   = substr((string) $s->end_time, 0, 5);
            return "- {$day} : {$start} – {$end}";
        });

        return "Vos créneaux de disponibilité :\n" . $lines->implode("\n");
    }

    // ──────────────────────────────────────────────────────
    // Admin actions
    // ──────────────────────────────────────────────────────

    private function platformKpis(): string
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth();

        $todayBookings = Booking::query()
            ->whereDate('created_at', $today)
            ->count();

        $activeMissions = Mission::query()
            ->whereIn('status', ['confirmed', 'en_route', 'sur_place', 'in_progress', 'on_route', 'on_site'])
            ->count();

        $monthRevenue = (float) ProviderPayout::query()
            ->where('status', ProviderPayout::STATUS_PAID)
            ->where('updated_at', '>=', $monthStart)
            ->sum('amount');

        $openDisputes = $this->countOpenDisputes();

        return implode("\n", [
            "KPIs plateforme (" . now()->format('d/m/Y H:i') . ") :",
            "- Réservations créées aujourd'hui : {$todayBookings}",
            "- Missions actives en ce moment : {$activeMissions}",
            "- Revenus ce mois (virements validés) : " . number_format($monthRevenue, 2, ',', ' ') . " €",
            "- Litiges ouverts : {$openDisputes}",
        ]);
    }

    private function tradeStats(): string
    {
        try {
            $rows = Booking::query()
                ->join('service_catalogs', 'bookings.service_catalog_id', '=', 'service_catalogs.id')
                ->selectRaw('service_catalogs.name as trade_name, COUNT(*) as total')
                ->groupBy('service_catalogs.id', 'service_catalogs.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            if ($rows->isEmpty()) {
                return "Aucune donnée de réservation par métier disponible.";
            }

            $lines = $rows->map(fn ($r) => "- {$r->trade_name} : {$r->total} réservation(s)");
            return "Réservations par métier (top 10) :\n" . $lines->implode("\n");
        } catch (\Throwable) {
            return "Statistiques par métier temporairement indisponibles.";
        }
    }

    private function countOpenDisputes(): int
    {
        try {
            return \App\Models\ComplaintCase::query()
                ->whereIn('status', ['open', 'pending', 'under_review', 'escalated'])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
