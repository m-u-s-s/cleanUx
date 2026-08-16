<?php

namespace App\Services\FaceCheck;

use App\Models\Booking;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * LE VERROU DE DISPATCH — dans le SQL, comme les autres.
 *
 * Il vit au même endroit que `verification_status` et `ConduiteRequirements` : dans la requête
 * elle-même. Un contrôle appliqué après coup se rattrape par un repli le jour où il vide la liste,
 * et ce dépôt en a déjà fait l'expérience.
 *
 * CE QU'IL EXCLUT, ET CE QU'IL N'EXCLUT PAS. Il écarte les états DÉFINITIFS : jamais enrôlé,
 * consentement retiré, bloqué. Il n'écarte PAS celui dont le contrôle est simplement dû — et c'est
 * délibéré. Un prestataire dont l'échéance vient de tomber n'a rien fait de mal ; l'écarter
 * silencieusement du dispatch le priverait de missions sans qu'aucun écran ne le lui dise, ce qui
 * est exactement l'angle mort déjà connu de `verification_status` sur ce dépôt. Il sera arrêté à
 * la porte qu'il traverse vraiment — mise en ligne, acceptation, départ — où on peut le lui
 * expliquer et lui proposer de le faire tout de suite.
 */
class FaceCheckDispatchFilter
{
    public function __construct(
        private readonly FaceCheckRequirement $requirement,
    ) {}

    /**
     * @param  Builder<User>  $query
     */
    public function appliquerAuxCandidats(Builder $query, Booking $booking): void
    {
        if (! $this->requirement->appliesToBooking($booking)) {
            return;
        }

        $query->whereExists(function (QueryBuilder $sub): void {
            $sub->select(DB::raw(1))
                ->from('provider_face_profiles')
                ->whereColumn('provider_face_profiles.user_id', 'users.id')
                ->where('provider_face_profiles.status', ProviderFaceProfile::STATUS_ENROLLED)
                ->whereNull('provider_face_profiles.blocked_at')
                ->whereNull('provider_face_profiles.consent_withdrawn_at');
        });
    }
}
