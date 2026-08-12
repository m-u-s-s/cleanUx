<?php

namespace App\Services\Payments;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * CE QUE LE PRESTATAIRE TOUCHE, ET QUAND — dit d'une seule voix.
 *
 * Le montant vient de `CommissionService`, seule source de vérité du partage depuis 2026-06-11 :
 * le recalculer ici ferait exactement la divergence que cette unification a supprimée (le
 * portefeuille affichait une part différente de ce que Stripe transférait réellement).
 *
 * La DATE, elle, n'est qu'une annonce. Le modèle est la charge à destination : à la capture,
 * Stripe a déjà viré la part du prestataire sur son compte Connect. Aucun code de ce dépôt ne
 * déclenche de virement — en ajouter un paierait deux fois. Ce qui reste à courir est le
 * calendrier de versement du compte Connect vers la banque, appliqué par Stripe.
 */
class PayoutAnnouncementService
{
    public function __construct(
        protected CommissionService $commissionService,
    ) {}

    /**
     * Le prestataire à qui revient l'argent de cette mission.
     *
     * Le responsable d'abord, puis l'assignation acceptée : le renfort d'une équipe n'est pas le
     * destinataire du paiement, c'est son employeur qui l'est.
     */
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
            'taux_commission' => $commission['commission_rate'],
            'devise' => $commission['currency'],
            'date_transfert' => Carbon::now()->addDays($jours)->toDateString(),
            'delai_jours' => $jours,
        ];
    }
}
