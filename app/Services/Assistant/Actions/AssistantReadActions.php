<?php

namespace App\Services\Assistant\Actions;

use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\LoyaltyAccount;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\User;

/**
 * Read-only assistant actions.
 *
 * Each method returns a formatted French text string.
 */
class AssistantReadActions
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

    /**
     * Dispatch a read-only action by name and return its formatted French text.
     */
    public function execute(string $actionName, User $user, array $params = []): string
    {
        return match ($actionName) {
            'list_bookings' => $this->listBookings($user),
            'booking_detail' => $this->bookingDetail($user, $params['booking_id'] ?? null),
            'loyalty_balance' => $this->loyaltyBalance($user),
            'next_mission' => $this->nextMission($user),
            'earnings_summary' => $this->earningsSummary($user),
            'availability_slots' => $this->availabilitySlots($user),
            'platform_kpis' => $this->platformKpis(),
            'trade_stats' => $this->tradeStats(),
            default => "Action inconnue : {$actionName}",
        };
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
            $ref = $b->booking_reference ?? "#{$b->id}";
            $date = $b->scheduled_date ?? '—';
            $time = $b->scheduled_time ? substr((string) $b->scheduled_time, 0, 5) : '';
            $loc = trim(($b->city ?? ''));
            $loc = $loc ? " — {$loc}" : '';

            return "- {$ref} | {$b->status} | {$date} {$time}{$loc}";
        });

        return "Vos 5 dernières réservations :\n".$lines->implode("\n");
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

        $ref = $booking->booking_reference ?? "#{$booking->id}";
        $date = $booking->scheduled_date ?? '—';
        $time = $booking->scheduled_time ? substr((string) $booking->scheduled_time, 0, 5) : '—';
        $address = trim(implode(', ', array_filter([$booking->address, $booking->city])));
        $price = $booking->estimated_price ? number_format((float) $booking->estimated_price, 2, ',', ' ').' €' : '—';

        return implode("\n", [
            "Réservation {$ref} :",
            "- Statut : {$booking->status}",
            "- Date/heure : {$date} à {$time}",
            "- Adresse : {$address}",
            "- Prix estimé : {$price}",
            '- Paiement : '.($booking->payment_status ?? '—'),
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
            'Votre compte fidélité :',
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

        $start = $mission->planned_start_at?->format('d/m/Y à H:i') ?? '—';
        $address = $mission->rendezVous?->address ?? '—';

        return implode("\n", [
            'Prochaine mission :',
            "- Début prévu : {$start}",
            "- Adresse : {$address}",
            "- Statut : {$mission->status}",
        ]);
    }

    private function earningsSummary(User $user): string
    {
        $weekStart = now()->startOfWeek();
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
            'Résumé de vos revenus :',
            '- Cette semaine : '.number_format($thisWeek, 2, ',', ' ').' €',
            '- Ce mois : '.number_format($thisMonth, 2, ',', ' ').' €',
            '- En attente de virement : '.number_format($pending, 2, ',', ' ').' €',
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
            $day = self::WEEKDAYS_FR[$s->weekday] ?? "Jour {$s->weekday}";
            $start = substr((string) $s->start_time, 0, 5);
            $end = substr((string) $s->end_time, 0, 5);

            return "- {$day} : {$start} – {$end}";
        });

        return "Vos créneaux de disponibilité :\n".$lines->implode("\n");
    }

    // ──────────────────────────────────────────────────────
    // Admin actions
    // ──────────────────────────────────────────────────────

    private function platformKpis(): string
    {
        $today = now()->toDateString();
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
            'KPIs plateforme ('.now()->format('d/m/Y H:i').') :',
            "- Réservations créées aujourd'hui : {$todayBookings}",
            "- Missions actives en ce moment : {$activeMissions}",
            '- Revenus ce mois (virements validés) : '.number_format($monthRevenue, 2, ',', ' ').' €',
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
                return 'Aucune donnée de réservation par métier disponible.';
            }

            $lines = $rows->map(fn ($r) => "- {$r->trade_name} : {$r->total} réservation(s)");

            return "Réservations par métier (top 10) :\n".$lines->implode("\n");
        } catch (\Throwable) {
            return 'Statistiques par métier temporairement indisponibles.';
        }
    }

    public function countOpenDisputes(): int
    {
        try {
            return ComplaintCase::query()
                ->whereIn('status', ['open', 'pending', 'under_review', 'escalated'])
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
