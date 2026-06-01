<?php

namespace App\Services\Booking;

use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Résout et valide la sélection du prestataire par le client (3 paliers SP2) :
 *   - palier auto    : aucun presta imposé (preferred null)              → tous
 *   - palier favori  : presta déjà favori du client                      → tous
 *   - palier nouveau : presta NON favori (découverte)                    → premium only
 *
 * Retourne ce qu'on persiste sur la réservation.
 */
class ProviderSelectionResolver
{
    private const TYPES = ['independent', 'company', 'any'];

    /**
     * @param  array{provider_type_preference?:string, preferred_provider_user_id?:int|null}  $input
     * @return array{provider_type_preference:string, preferred_provider_user_id:?int}
     */
    public function resolve(User $client, array $input): array
    {
        $type = in_array($input['provider_type_preference'] ?? 'any', self::TYPES, true)
            ? $input['provider_type_preference']
            : 'any';

        $preferredId = $input['preferred_provider_user_id'] ?? null;

        if ($preferredId === null) {
            return ['provider_type_preference' => $type, 'preferred_provider_user_id' => null];
        }

        $isFavorite = BookingFavorite::query()
            ->where('client_user_id', $client->id)
            ->where('preferred_provider_user_id', $preferredId)
            ->exists();

        $profile = $client->customerProfile;
        $isPremium = $profile instanceof CustomerProfile && $profile->isPremium();
        if (! $isFavorite && ! $isPremium) {
            throw new AuthorizationException('Le choix d’un nouveau prestataire est réservé au pack Premium.');
        }

        return ['provider_type_preference' => $type, 'preferred_provider_user_id' => (int) $preferredId];
    }
}
