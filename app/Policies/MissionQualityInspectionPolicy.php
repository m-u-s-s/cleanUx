<?php

namespace App\Policies;

use App\Models\MissionQualityInspection;
use App\Models\User;
use App\Support\Quality\QualityInspectionAccess;

/** Authorization for Quality inspections (C1). */
class MissionQualityInspectionPolicy
{
    public function viewClient(User $user, MissionQualityInspection $inspection): bool
    {
        return QualityInspectionAccess::clientCanAccess($user, $inspection);
    }

    public function validateClient(User $user, MissionQualityInspection $inspection): bool
    {
        return QualityInspectionAccess::clientCanAccess($user, $inspection);
    }

    public function disputeClient(User $user, MissionQualityInspection $inspection): bool
    {
        return QualityInspectionAccess::clientCanAccess($user, $inspection);
    }

    public function viewProvider(User $user, MissionQualityInspection $inspection): bool
    {
        return QualityInspectionAccess::providerCanAccess($user, $inspection);
    }

    public function manageProvider(User $user, MissionQualityInspection $inspection): bool
    {
        return QualityInspectionAccess::providerCanAccess($user, $inspection);
    }
}
