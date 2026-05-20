<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service de gestion des favoris booking (rebook 1-click style Uber repeat order).
 *
 * Workflow :
 *   - createFromBooking : capture le snapshot d'un booking pour repeat plus tard
 *   - markUsed : incrémente compteur + last_used_at (analytics)
 *   - delete : retire un favori
 */
class BookingFavoriteService
{
    public function createFromBooking(User $client, Booking $booking, ?string $label = null): BookingFavorite
    {
        if ((int) $booking->client_id !== (int) $client->id) {
            throw ValidationException::withMessages([
                'booking' => ['Vous ne pouvez sauvegarder que vos propres bookings.'],
            ]);
        }

        // Idempotency : pas plus d'un favori par (client, source_booking)
        $existing = BookingFavorite::query()
            ->where('client_user_id', $client->id)
            ->where('source_booking_id', $booking->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $snapshot = [
            'address' => $booking->getAttribute('address')
                ?? $booking->getAttribute('adresse_complete')
                ?? data_get($booking, 'address_components.formatted'),
            'destination_lat' => $booking->getAttribute('destination_lat'),
            'destination_lng' => $booking->getAttribute('destination_lng'),
            'duree_estimee' => $booking->getAttribute('duree_estimee'),
            'devis_estime' => $booking->getAttribute('devis_estime'),
            'commentaire_client' => $booking->getAttribute('commentaire_client'),
            'zones_specifiques' => $booking->getAttribute('zones_specifiques'),
            'options_prestation' => $booking->getAttribute('options_prestation'),
            'materiel_fournit' => $booking->getAttribute('materiel_fournit'),
        ];

        return DB::transaction(function () use ($client, $booking, $label, $snapshot) {
            return BookingFavorite::query()->create([
                'client_user_id' => $client->id,
                'label' => $label ?: $this->buildDefaultLabel($booking),
                'source_booking_id' => $booking->id,
                'preferred_provider_user_id' => $booking->employe_id ?? $booking->assigned_employee_id ?? null,
                'trade_id' => $booking->trade_id ?? null,
                'service_zone_id' => $booking->service_zone_id ?? null,
                'snapshot' => $snapshot,
            ]);
        });
    }

    public function markUsed(BookingFavorite $favorite): BookingFavorite
    {
        $favorite->update([
            'use_count' => (int) $favorite->use_count + 1,
            'last_used_at' => now(),
        ]);
        return $favorite->fresh();
    }

    public function delete(BookingFavorite $favorite): void
    {
        $favorite->delete();
    }

    protected function buildDefaultLabel(Booking $booking): string
    {
        $parts = [];
        if ($booking->getAttribute('adresse_complete')) {
            $parts[] = mb_substr((string) $booking->adresse_complete, 0, 40);
        }
        if ($booking->getAttribute('duree_estimee')) {
            $parts[] = $booking->duree_estimee . 'min';
        }
        return $parts ? implode(' · ', $parts) : 'Favori #' . $booking->id;
    }
}
