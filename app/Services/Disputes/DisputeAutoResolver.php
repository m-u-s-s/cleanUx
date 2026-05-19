<?php

namespace App\Services\Disputes;

use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;

/**
 * Règles d'auto-résolution simples appliquées juste après l'ouverture
 * d'une dispute. Permet de fluidifier les cas évidents (no-show provider,
 * doublon paiement, etc.) sans intervention humaine.
 *
 * Les règles sont CONSERVATIVES : si moindre doute, on ne fait rien et on
 * laisse le SAV humain trancher.
 */
class DisputeAutoResolver
{
    public function maybeAutoResolve(ComplaintCase $case): ?DisputeResolution
    {
        if (! Config::get('disputes.auto_resolution.enabled', true)) {
            return null;
        }

        if (! $this->isEligibleCategory($case)) {
            return null;
        }

        $booking = $case->booking ?? $case->rendezVous;
        if (! $booking) {
            return null;
        }

        $amount = (float) ($booking->devis_estime ?? 0);
        $maxAuto = (float) Config::get('disputes.auto_resolution.auto_refund_max_amount', 200);
        if ($amount > $maxAuto || $amount <= 0) {
            return null;
        }

        if (! $this->matchesNoShowPattern($case, $booking)) {
            return null;
        }

        $resolution = DisputeResolution::create([
            'complaint_case_id' => $case->id,
            'resolution_type' => DisputeResolution::TYPE_REFUND_FULL,
            'amount' => $amount,
            'currency' => $booking->currency ?? 'EUR',
            'explanation' => "Auto-résolution : no-show provider détecté (booking jamais commencé après l'horaire prévu).",
            'status' => DisputeResolution::STATUS_PROPOSED,
            'metadata' => [
                'auto' => true,
                'reason' => 'provider_no_show_pattern',
            ],
        ]);

        $case->update([
            'auto_resolved' => true,
            'meta' => array_merge((array) $case->meta, [
                'auto_resolution_id' => $resolution->id,
            ]),
        ]);

        ActivityLogger::log('dispute.auto_resolution_proposed', $case, [
            'resolution_id' => $resolution->id,
            'amount' => $amount,
        ]);

        return $resolution;
    }

    protected function isEligibleCategory(ComplaintCase $case): bool
    {
        $eligible = (array) Config::get('disputes.auto_resolution.auto_refund_categories', []);
        return in_array($case->category, $eligible, true);
    }

    /**
     * Heuristique simple : booking marqué confirmé mais jamais mission_started_at
     * + plus de 30 min après l'heure prévue.
     */
    protected function matchesNoShowPattern(ComplaintCase $case, $booking): bool
    {
        if ($case->category !== ComplaintCase::CATEGORY_NO_SHOW) {
            return false;
        }

        if (! empty($booking->mission_started_at)) {
            return false;
        }

        if (in_array($booking->status, ['en_attente', 'pending'], true)) {
            return false;
        }

        return true;
    }
}
