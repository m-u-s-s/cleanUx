<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\Booking;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

/**
 * Tool de lecture : liste les réservations de l'utilisateur courant.
 *
 * Lecture seule → executesImmediately = true (pas de confirmation requise).
 */
class ListMyBookingsTool implements AssistantTool
{
    public function name(): string
    {
        return 'list_my_bookings';
    }

    public function description(): string
    {
        return "Liste les réservations de l'utilisateur connecté. "
            . "Utilise ce tool quand l'utilisateur demande 'mes réservations', 'mes missions', "
            . "'mes prochains rendez-vous', ou veut connaître l'état d'une réservation. "
            . "Retourne au maximum 10 résultats triés par date.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type'        => 'string',
                    'enum'        => ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'all'],
                    'description' => "Filtrer par statut. 'all' (défaut) retourne tous les statuts non-annulés.",
                ],
                'limit' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 10,
                    'description' => "Nombre maximum de résultats (1-10, défaut 5).",
                ],
            ],
            'required' => [],
        ];
    }

    public function authorize(User $user): bool
    {
        // Tout utilisateur authentifié peut lister SES bookings
        return true;
    }

    public function executesImmediately(): bool
    {
        return true;
    }

    public function execute(User $user, array $input): array
    {
        $status = $input['status'] ?? 'all';
        $limit  = min((int) ($input['limit'] ?? 5), 10);

        $query = Booking::query()
            ->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            })
            ->orderByDesc('scheduled_date')
            ->orderByDesc('scheduled_time')
            ->limit($limit);

        if ($status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->whereNotIn('status', ['cancelled']);
        }

        $bookings = $query->with(['serviceCatalog:id,name'])->get();

        return [
            'count'    => $bookings->count(),
            'bookings' => $bookings->map(fn ($b) => [
                'id'            => $b->id,
                'reference'     => $b->booking_reference,
                'service'       => $b->serviceCatalog?->name,
                'status'        => $b->status,
                'scheduled_at'  => $b->scheduled_date
                    ? $b->scheduled_date->format('d/m/Y') . ' ' . substr((string) $b->scheduled_time, 0, 5)
                    : null,
                'address'       => $b->display_address,
                'city'          => $b->display_city,
                'price'         => $b->estimated_price ? number_format((float) $b->estimated_price, 2, ',', ' ') . ' €' : null,
            ])->all(),
        ];
    }
}
