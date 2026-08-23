<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\BookingFavorite;
use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Résout et valide la sélection du prestataire par le client. */
class ProviderSelectionResolver
{
    private const TYPES = ['independent', 'company', 'any'];

    /**
     * @param  array{provider_type_preference?:string, preferred_provider_user_id?:int|null, assigned_provider_organization_id?:int|null}  $input
     * @param  Booking|null  $context  Booking (sauvegardé ou transitoire) portant service_zone_id +
     *                                 service_catalog_id, utilisé pour valider l'éligibilité société.
     * @return array{provider_type_preference:string, preferred_provider_user_id:?int, assigned_provider_organization_id:?int}
     */
    public function resolve(User $client, array $input, ?Booking $context = null): array
    {
        $requestedType = $input['provider_type_preference'] ?? 'any';
        $type = in_array($requestedType, self::TYPES, true) ? $requestedType : 'any';

        $profile = $client->customerProfile;
        $isPremium = $profile instanceof CustomerProfile && $profile->isPremium();

        // ── Palier SOCIÉTÉ (SP3) : prime sur le worker nominatif ───────────────
        $orgId = $input['assigned_provider_organization_id'] ?? null;
        if ($orgId !== null) {
            $orgId = (int) $orgId;

            if (! $isPremium) {
                throw new AuthorizationException('Le choix d’une société est réservé au pack Premium.');
            }

            if ($context instanceof Booking
                && ! app(EligibleCompaniesResolver::class)->isEligible($context, $orgId)) {
                throw new AuthorizationException('Société non disponible pour cette réservation.');
            }

            // org et worker précis sont mutuellement exclusifs : l'org prime.
            return [
                'provider_type_preference' => $type,
                'preferred_provider_user_id' => null,
                'assigned_provider_organization_id' => $orgId,
            ];
        }

        // ── Paliers WORKER (SP2) ───────────────────────────────────────────────
        $preferredId = $input['preferred_provider_user_id'] ?? null;

        if ($preferredId === null) {
            return [
                'provider_type_preference' => $type,
                'preferred_provider_user_id' => null,
                'assigned_provider_organization_id' => null,
            ];
        }

        $isFavorite = BookingFavorite::query()
            ->where('client_user_id', $client->id)
            ->where('preferred_provider_user_id', $preferredId)
            ->exists();

        if (! $isFavorite && ! $isPremium) {
            throw new AuthorizationException('Le choix d’un nouveau prestataire est réservé au pack Premium.');
        }

        return [
            'provider_type_preference' => $type,
            'preferred_provider_user_id' => (int) $preferredId,
            'assigned_provider_organization_id' => null,
        ];
    }
}
