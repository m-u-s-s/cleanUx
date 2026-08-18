<?php

namespace App\Services\Cancellation;

use App\Models\BookingCancellationV2;
use App\Models\CancellationExemptReason;
use Illuminate\Support\Carbon;

/**
 * « PAS LA PREMIÈRE FOIS, MAIS SI C'EST FRÉQUENT » — la règle du porteur, et une colonne qui
 * l'attendait depuis toujours.
 *
 * `cancellation_exempt_reasons.max_per_user_per_30d` est déclarée, semée à 2 pour l'urgence
 * médicale et 3 côté prestataire, et **personne ne l'appliquait**. Une urgence médicale exonérait
 * donc autant de fois que voulu — c'est-à-dire que le motif le plus généreux du barème était sans
 * plafond.
 *
 * ── CE QUE LE DÉPASSEMENT FAIT, ET NE FAIT PAS ───────────────────────────────────────────────
 *
 * Il retire l'EXEMPTION, pas le motif. La réponse reste enregistrée dans `reason_code` : on doit
 * pouvoir relire qu'une personne a invoqué l'urgence médicale six fois en un mois, précisément
 * parce que c'est le motif qui a cessé d'exonérer.
 *
 * Et il ne bloque rien : l'annulation se fait, aux conditions normales du palier.
 */
class CancellationExemptQuota
{
    /**
     * Ce motif peut-il encore exonérer cette personne ?
     *
     * `null` ou `0` sur `max_per_user_per_30d` veut dire « sans plafond » — c'est le cas de la
     * force majeure et de l'absence du prestataire, qui ne dépendent pas de celui qui annule.
     */
    public function exonereEncore(CancellationExemptReason $motif, ?int $userId): bool
    {
        $plafond = (int) ($motif->max_per_user_per_30d ?? 0);

        if ($plafond <= 0 || $userId === null) {
            return true;
        }

        return $this->usagesRecents($motif->reason_code, $userId) < $plafond;
    }

    /**
     * Combien de fois cette personne a-t-elle DÉJÀ été exonérée sur ce motif en trente jours.
     *
     * On compte les exemptions RÉELLEMENT APPLIQUÉES (`exempt_applied`), jamais les mentions du
     * motif : une annulation où le plafond avait déjà mordu ne doit pas compter une seconde fois
     * contre la personne.
     */
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
