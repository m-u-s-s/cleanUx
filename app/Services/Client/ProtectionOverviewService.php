<?php

namespace App\Services\Client;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\ComplaintCase;
use App\Models\User;
use App\Services\CancellationV2\CancellationPolicyResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** « MA PROTECTION » (E6) — la vitrine des recours d'un client. */
class ProtectionOverviewService
{
    /**
     * @return array<string, mixed>
     */
    public function pour(User $client): array
    {
        $aVenir = Booking::query()
            ->where('client_id', $client->id)
            ->whereNotIn('status', ['annule', 'cancelled', 'completed', 'termine'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', Carbon::now())
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get(['id', 'booking_reference', 'scheduled_at', 'devis_estime', 'trade_id']);

        return [
            'insurance' => $this->assurances($client),
            'cancellation' => $this->annulations($aVenir),
            'disputes' => $this->litiges($client),
        ];
    }

    /**
     * Les couvertures ACTIVES, et rien d'autre.
     *
     * @return array<string, mixed>
     */
    protected function assurances(User $client): array
    {
        $polices = BookingInsurance::query()
            ->where('user_id', $client->id)
            ->where('status', 'active')
            ->with('booking:id,booking_reference,scheduled_at')
            ->latest()
            ->get();

        return [
            'active_count' => $polices->count(),
            'total_coverage_cents' => (int) $polices->sum('coverage_amount_cents'),
            'policies' => $polices->map(fn (BookingInsurance $police) => [
                'id' => $police->id,
                'policy_number' => $police->policy_number,
                'coverage_amount_cents' => (int) $police->coverage_amount_cents,
                'effective_until' => $police->effective_until?->toDateString(),
                'booking_reference' => $police->booking?->booking_reference,
            ])->all(),
        ];
    }

    /**
     * Ce que coûterait une annulation MAINTENANT, réservation par réservation.
     *
     * @param  Collection<int, Booking>  $aVenir
     * @return array<string, mixed>
     */
    protected function annulations(Collection $aVenir): array
    {
        $resolveur = app(CancellationPolicyResolver::class);
        $lignes = [];

        foreach ($aVenir as $booking) {
            $heuresAvant = $booking->scheduled_at !== null
                ? max(0, (int) Carbon::now()->diffInHours($booking->scheduled_at, false))
                : 0;

            try {
                $politique = $resolveur->resolveForBooking(
                    (int) $booking->id,
                    'client',
                    $heuresAvant,
                );
            } catch (\Throwable $e) {
                // SOFT-FAIL : une politique absente pour un métier ne doit pas faire tomber la page.
                report($e);

                continue;
            }

            $lignes[] = [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'scheduled_at' => $booking->scheduled_at?->toIso8601String(),
                'hours_before' => $heuresAvant,
                // Rendu tel que le moteur le calcule : recopier sa logique ici la ferait diverger
                // dès la première évolution du barème.
                'policy' => $politique,
            ];
        }

        return [
            'upcoming_count' => $aVenir->count(),
            'quotes' => $lignes,
        ];
    }

    /**
     * Les litiges, ouverts d'abord.
     *
     * @return array<string, mixed>
     */
    protected function litiges(User $client): array
    {
        $dossiers = ComplaintCase::query()
            ->where('client_id', $client->id)
            ->latest()
            ->limit(20)
            ->get(['id', 'reference', 'subject', 'status', 'created_at', 'resolved_at']);

        $ouverts = $dossiers->reject(fn (ComplaintCase $dossier) => in_array(
            $dossier->status,
            [ComplaintCase::STATUS_RESOLVED, ComplaintCase::STATUS_CLOSED],
            true,
        ));

        return [
            'open_count' => $ouverts->count(),
            'cases' => $dossiers->map(fn (ComplaintCase $dossier) => [
                'id' => $dossier->id,
                'reference' => $dossier->reference,
                'subject' => $dossier->subject,
                'status' => $dossier->status,
                'opened_at' => $dossier->created_at?->toDateString(),
                'resolved_at' => $dossier->resolved_at?->toDateString(),
            ])->all(),
        ];
    }
}
