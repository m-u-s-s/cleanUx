<?php

namespace App\Services\Assistant\Actions;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Models\ProviderPresence;
use App\Models\User;

/** Write assistant actions (those that mutate data). */
class AssistantWriteActions
{
    /** Route a write action name to its implementation. */
    public function dispatch(string $actionName, User $user, array $params): string
    {
        return match ($actionName) {
            'create_booking' => $this->doCreateBooking($user, $params),
            'cancel_booking' => $this->doCancelBooking($user, $params),
            'resolve_dispute' => $this->doResolveDispute($user, $params),
            'update_availability' => $this->doUpdateAvailability($user, $params),
            default => "Action d'écriture inconnue : {$actionName}",
        };
    }

    /** Build a human-readable French confirmation summary for a write action. */
    public function buildConfirmationSummary(string $actionName, User $user, array $params): string
    {
        return match ($actionName) {
            'create_booking' => $this->summaryCreateBooking($params),
            'cancel_booking' => $this->summaryCancelBooking($user, $params),
            'resolve_dispute' => $this->summaryResolveDispute($params),
            default => "Confirmer l'action : {$actionName} ?",
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
            'customer_user_id' => $user->id,
            'service_catalog_id' => (int) $params['service_catalog_id'],
            'address' => $params['address'],
            'city' => $params['city'],
            'postal_code' => $params['postal_code'],
            'scheduled_date' => $params['scheduled_date'],
            'scheduled_time' => $params['scheduled_time'],
            'status' => 'en_attente',
            'booking_reference' => 'CUX-'.strtoupper(substr(uniqid(), -6)),
        ]);

        $ref = $booking->booking_reference;

        return "Réservation {$ref} créée avec succès pour le {$params['scheduled_date']} à {$params['scheduled_time']} à {$params['city']}.";
    }

    private function doCancelBooking(User $user, array $params): string
    {
        $bookingId = $params['booking_id'] ?? null;

        if (! $bookingId) {
            return 'Erreur : identifiant de réservation manquant.';
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
            // Prefix with "Erreur" so the executor records the action as FAILED, not EXECUTED:
            // an already-cancelled / inaccessible booking is a non-success outcome.
            return "Erreur : réservation introuvable, déjà annulée, ou vous n'y avez pas accès.";
        }

        $booking->update(['status' => 'annule']);
        $ref = $booking->booking_reference ?? "#{$booking->id}";

        return "Réservation {$ref} annulée avec succès.";
    }

    private function doResolveDispute(User $user, array $params): string
    {
        $disputeId = $params['dispute_id'] ?? null;
        $resolution = $params['resolution'] ?? null;

        if (! $disputeId || ! $resolution) {
            return 'Erreur : identifiant du litige et texte de résolution requis.';
        }

        $dispute = ComplaintCase::query()
            ->whereNotIn('status', [ComplaintCase::STATUS_RESOLVED, ComplaintCase::STATUS_CLOSED])
            ->find((int) $disputeId);

        if (! $dispute) {
            return "Litige #{$disputeId} introuvable ou déjà résolu.";
        }

        DisputeResolution::create([
            'complaint_case_id' => $dispute->id,
            'resolution_type' => DisputeResolution::TYPE_OTHER,
            'explanation' => $resolution,
            'issued_by_user_id' => $user->id,
            'status' => DisputeResolution::STATUS_APPLIED,
            'applied_at' => now(),
        ]);

        $dispute->update([
            'status' => ComplaintCase::STATUS_RESOLVED,
            'resolved_at' => now(),
            'admin_response' => $resolution,
        ]);

        return "Litige #{$disputeId} ({$dispute->reference}) résolu avec succès.";
    }

    /** update_availability is low-risk: no confirmation required. */
    private function doUpdateAvailability(User $user, array $params): string
    {
        $requestedStatus = $params['status'] ?? null;
        $statusMap = [
            'online' => ProviderPresence::STATUS_ONLINE,
            'offline' => ProviderPresence::STATUS_OFFLINE,
            'break' => ProviderPresence::STATUS_ON_BREAK,
        ];

        if (! $requestedStatus || ! isset($statusMap[$requestedStatus])) {
            return 'Statut invalide. Valeurs acceptées : online, offline, break.';
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
            return 'Annuler cette réservation ?';
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

        $ref = $booking->booking_reference ?? "#{$bookingId}";
        $date = $booking->scheduled_date ?? '—';
        $time = $booking->scheduled_time ? substr((string) $booking->scheduled_time, 0, 5) : '';

        return "Annuler la réservation {$ref} du {$date}".($time ? " à {$time}" : '').' ?';
    }

    private function summaryResolveDispute(array $params): string
    {
        $disputeId = $params['dispute_id'] ?? '—';
        $resolution = mb_strimwidth($params['resolution'] ?? '', 0, 80, '…');

        return "Résoudre le litige #{$disputeId} avec la résolution : « {$resolution} » ?";
    }
}
