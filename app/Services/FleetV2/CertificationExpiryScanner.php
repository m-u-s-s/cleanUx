<?php

namespace App\Services\FleetV2;

use App\Models\FleetCertification;

class CertificationExpiryScanner
{
    /**
     * Met à jour les statuts certifications selon expires_at :
     *   - expired (déjà passé)
     *   - expiring_soon (dans les N jours)
     *   - active (sinon)
     * Retourne le compte de chaque transition.
     */
    public function scanAndUpdate(?int $expiringSoonDays = null): array
    {
        $expiringSoonDays ??= (int) config('fleet_v2.expiring_soon_days', 30);
        $now = now();
        $soonThreshold = $now->copy()->addDays($expiringSoonDays);

        $expired = FleetCertification::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', $now)
            ->whereNotIn('status', [FleetCertification::STATUS_EXPIRED, FleetCertification::STATUS_REVOKED])
            ->update(['status' => FleetCertification::STATUS_EXPIRED]);

        $expiringSoon = FleetCertification::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$now, $soonThreshold])
            ->whereNotIn('status', [FleetCertification::STATUS_EXPIRED, FleetCertification::STATUS_REVOKED])
            ->update(['status' => FleetCertification::STATUS_EXPIRING_SOON]);

        // Reactivate : expiring_soon OU expired → active si expires_at maintenant > seuil.
        // Couvre le cas renouvellement d'un document après son expiration (revoked reste figé).
        $reactivated = FleetCertification::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $soonThreshold)
            ->whereIn('status', [FleetCertification::STATUS_EXPIRING_SOON, FleetCertification::STATUS_EXPIRED])
            ->update(['status' => FleetCertification::STATUS_ACTIVE]);

        return [
            'expired' => $expired,
            'expiring_soon' => $expiringSoon,
            'reactivated' => $reactivated,
        ];
    }

    /**
     * Retourne les certifications qui vont expirer dans les N jours (pour alertes admin/email).
     *
     * @return \Illuminate\Support\Collection<int, FleetCertification>
     */
    public function listExpiringSoon(?int $days = null): \Illuminate\Support\Collection
    {
        $days ??= (int) config('fleet_v2.expiring_soon_days', 30);
        return FleetCertification::query()
            ->expiringWithin($days)
            ->orderBy('expires_at')
            ->get();
    }
}
