<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\Booking;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

class CancelBookingTool implements AssistantTool
{
    public function name(): string
    {
        return 'cancel_booking';
    }

    public function description(): string
    {
        return "Annule une réservation existante du client. "
            . "Demande confirmation explicite avant d'appeler ce tool. "
            . "Vérifie que le booking_id appartient bien à l'utilisateur (sinon erreur).";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'booking_id' => [
                    'type'        => 'integer',
                    'description' => "ID de la réservation à annuler.",
                ],
                'reason' => [
                    'type'        => 'string',
                    'maxLength'   => 500,
                    'description' => "Motif d'annulation (sera enregistré).",
                ],
            ],
            'required' => ['booking_id', 'reason'],
        ];
    }

    public function authorize(User $user): bool
    {
        return true; // ownership check fait dans execute()
    }

    public function executesImmediately(): bool
    {
        return false; // confirmation requise
    }

    public function execute(User $user, array $input): array
    {
        $bookingId = (int) $input['booking_id'];
        $reason    = (string) ($input['reason'] ?? '');

        $booking = Booking::find($bookingId);

        if (! $booking) {
            return [
                'ok'    => false,
                'error' => "Réservation #{$bookingId} introuvable.",
            ];
        }

        // Ownership : doit être customer ou client_id ou même org
        $isOwner = (int) $booking->customer_user_id === (int) $user->id
            || (int) $booking->client_id === (int) $user->id;

        $isOrgMember = $user->organization_account_id
            && (int) $booking->customer_organization_id === (int) $user->organization_account_id
            && app(\App\Services\PermissionService::class)
                ->can($user, 'bookings.cancel', $user->currentOrganization);

        if (! $isOwner && ! $isOrgMember && ! $user->isAdmin()) {
            return [
                'ok'    => false,
                'error' => "Vous n'êtes pas autorisé à annuler cette réservation.",
            ];
        }

        if ($booking->isCancelled()) {
            return [
                'ok'      => true,
                'message' => "Cette réservation est déjà annulée.",
            ];
        }

        if ($booking->isCompleted()) {
            return [
                'ok'    => false,
                'error' => "Impossible d'annuler une réservation déjà terminée.",
            ];
        }

        $booking->update([
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancelled_by'        => $user->id,
            'cancellation_reason' => $reason,
        ]);

        return [
            'ok'                => true,
            'booking_id'        => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'message'           => "Réservation annulée. Un email de confirmation a été envoyé.",
        ];
    }
}
