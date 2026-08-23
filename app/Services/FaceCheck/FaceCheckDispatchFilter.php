<?php

namespace App\Services\FaceCheck;

use App\Models\Booking;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/** LE VERROU DE DISPATCH — dans le SQL, comme les autres. */
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
