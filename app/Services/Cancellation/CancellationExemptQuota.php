<?php

namespace App\Services\Cancellation;

use App\Models\BookingCancellationV2;
use App\Models\CancellationExemptReason;
use Illuminate\Support\Carbon;

/** « PAS LA PREMIÈRE FOIS, MAIS SI C'EST FRÉQUENT » — la règle du porteur, et une colonne qui l'attendait depuis toujours. */
class CancellationExemptQuota
{
    /** Ce motif peut-il encore exonérer cette personne ? */
    public function exonereEncore(CancellationExemptReason $motif, ?int $userId): bool
    {
        $plafond = (int) ($motif->max_per_user_per_30d ?? 0);

        if ($plafond <= 0 || $userId === null) {
            return true;
        }

        return $this->usagesRecents($motif->reason_code, $userId) < $plafond;
    }

    /** Combien de fois cette personne a-t-elle DÉJÀ été exonérée sur ce motif en trente jours. */
    public function usagesRecents(string $reasonCode, int $userId): int
    {
        return BookingCancellationV2::query()
            ->where('cancelled_by_user_id', $userId)
            ->where('reason_code', $reasonCode)
            ->where('exempt_applied', true)
            ->where('cancelled_at', '>=', Carbon::now()->subDays(30))
            ->count();
    }
}
