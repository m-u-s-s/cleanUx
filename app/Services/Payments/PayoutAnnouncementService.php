<?php

namespace App\Services\Payments;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Carbon;

/** CE QUE LE PRESTATAIRE TOUCHE, ET QUAND — dit d'une seule voix. */
class PayoutAnnouncementService
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    /** Le prestataire à qui revient l'argent de cette mission. */
    public function beneficiaire(Mission $mission): ?User
    {
        $id = $mission->lead_provider_user_id
            ?? $mission->assignments()->where('assignment_status', 'accepted')->value('user_id');

        return $id ? User::find($id) : null;
    }

    /**
     * @return array<string, mixed>|null Null si la mission ne porte aucun montant exploitable.
     */
    public function pour(Mission $mission): ?array
    {
        $booking = $mission->booking;

        if (! $booking) {
            return null;
        }

        $commission = $this->commissionService->calculateForBooking($booking);

        if ($commission['total_cents'] <= 0) {
            return null;
        }

        $jours = max(0, (int) config('brio.payout_delay_days', 7));

        return [
            'montant_prestataire' => round($commission['provider_payout_cents'] / 100, 2),
            'commission_plateforme' => round($commission['platform_fee_cents'] / 100, 2),
            'total' => round($commission['total_cents'] / 100, 2),
            // LE TAUX ANNONCÉ EST CELUI QUI A ÉTÉ RETENU. Ce champ portait le taux de la grille.
            'taux_commission' => $commission['effective_commission_rate'],
            'taux_grille' => $commission['commission_rate'],
            'plancher_applique' => $commission['minimum_applied'],
            'devise' => $commission['currency'],
            'date_transfert' => Carbon::now()->addDays($jours)->toDateString(),
            'delai_jours' => $jours,
        ];
    }
}
