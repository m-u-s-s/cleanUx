<?php

namespace App\Services\Client\Calendar;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6.1 — Service de reprogrammation d'un booking via drag-and-drop.
 *
 * Vérifie l'ownership, valide la nouvelle date/heure, met à jour, et logue
 * dans booking_reschedule_history pour audit.
 *
 * Lance une exception DomainException si la reprog n'est pas autorisée
 * (booking déjà terminé, dans le passé, etc.).
 */
class BookingRescheduleService
{
    /**
     * Reprogramme un booking à une nouvelle date/heure.
     *
     * @throws \DomainException si non autorisé
     */
    public function reschedule(
        User $user,
        Booking $booking,
        Carbon $newDate,
        ?string $newTime = null,
        ?string $reason = null,
    ): Booking {
        $this->authorize($user, $booking);
        $this->validateNewSchedule($booking, $newDate, $newTime);

        return DB::transaction(function () use ($user, $booking, $newDate, $newTime, $reason) {
            $oldDate = $booking->scheduled_date;
            $oldTime = $booking->scheduled_time;

            $booking->update([
                'scheduled_date' => $newDate->toDateString(),
                'scheduled_time' => $newTime ?: $booking->scheduled_time,
            ]);

            // Audit dans la table d'historique (créée par la migration Phase 6.1)
            $this->logHistory($user, $booking, $oldDate, $oldTime, $newDate, $newTime, $reason);

            return $booking->fresh();
        });
    }

    /**
     * Vérifie que l'utilisateur a le droit de reprogrammer ce booking.
     */
    protected function authorize(User $user, Booking $booking): void
    {
        // Admin plateforme : OK
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return;
        }

        // Le user doit être client direct ou membre de l'org cliente
        $isOwner = (int) ($booking->customer_user_id ?? 0) === (int) $user->id
            || (int) ($booking->client_id ?? 0) === (int) $user->id;

        $isOrgMember = $booking->customer_organization_id
            && $user->organization_account_id
            && (int) $booking->customer_organization_id === (int) $user->organization_account_id;

        if (! $isOwner && ! $isOrgMember) {
            throw new \DomainException("Vous n'avez pas accès à cette réservation.");
        }
    }

    /**
     * Vérifie que la nouvelle date est cohérente :
     *   - dans le futur (au moins +30 minutes)
     *   - le booking n'est pas déjà terminé/annulé/sur place
     *   - la date n'est pas plus de 6 mois dans le futur
     */
    protected function validateNewSchedule(Booking $booking, Carbon $newDate, ?string $newTime): void
    {
        // Bookings finals : pas reprogrammables
        $finalStatuses = ['termine', 'completed', 'done', 'annule', 'cancelled', 'refuse', 'sur_place', 'on_site'];
        if (in_array((string) $booking->status, $finalStatuses, true)) {
            throw new \DomainException(
                "Cette réservation ne peut plus être reprogrammée (statut: {$booking->status})."
            );
        }

        // Construire le datetime cible
        $time = $newTime ?: ($booking->scheduled_time
            ? Carbon::parse($booking->scheduled_time)->format('H:i')
            : '08:00');

        $target = Carbon::parse($newDate->toDateString() . ' ' . $time);

        // Pas dans le passé
        if ($target->lessThan(now()->addMinutes(30))) {
            throw new \DomainException(
                "La nouvelle date doit être au moins 30 minutes dans le futur."
            );
        }

        // Pas trop loin dans le futur (sécurité contre erreur de drag)
        if ($target->greaterThan(now()->addMonths(6))) {
            throw new \DomainException(
                "La nouvelle date ne peut pas dépasser 6 mois dans le futur."
            );
        }
    }

    protected function logHistory(
        User $user,
        Booking $booking,
        $oldDate,
        $oldTime,
        Carbon $newDate,
        ?string $newTime,
        ?string $reason,
    ): void {
        try {
            DB::table('booking_reschedule_history')->insert([
                'booking_id'    => $booking->id,
                'user_id'       => $user->id,
                'old_date'      => $oldDate instanceof Carbon ? $oldDate->toDateString() : (string) $oldDate,
                'old_time'      => $oldTime ? Carbon::parse($oldTime)->format('H:i:s') : null,
                'new_date'      => $newDate->toDateString(),
                'new_time'      => $newTime ? $newTime . ':00' : null,
                'reason'        => $reason,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // La table peut ne pas exister yet → log mais ne bloque pas
            \Log::warning('booking_reschedule_history insert failed: ' . $e->getMessage());
        }
    }
}
