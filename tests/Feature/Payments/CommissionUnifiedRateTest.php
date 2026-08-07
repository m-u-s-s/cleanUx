<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Payments\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Décision produit 2026-06-11 : commission = TAUX UNIQUE au lancement.
 * Le taux négocié par prestataire (ProviderProfile.commission_rate) reste en base
 * mais NE DOIT PAS être appliqué tant que brio.use_negotiated_commission est off.
 * Objectif : une seule source de vérité, alignée sur ce que Stripe prélève réellement.
 */
class CommissionUnifiedRateTest extends TestCase
{
    use RefreshDatabase;

    private function bookingWithNegotiatedProvider(float $negotiatedRate, float $devis = 100.00): Booking
    {
        $employe = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $employe->id,
            'commission_rate' => $negotiatedRate,
        ]);

        return Booking::factory()->create([
            'employe_id' => $employe->id,
            'assigned_provider_user_id' => $employe->id,
            'devis_estime' => $devis,
        ])->fresh(['employe.providerProfile', 'assignedProvider.providerProfile']);
    }

    public function test_ignores_negotiated_provider_rate_when_unified_commission_enabled(): void
    {
        config([
            'brio.platform_fee_percent' => 20,
            'brio.use_negotiated_commission' => false,
        ]);

        // Prestataire avec un taux négocié de 10 % — doit être IGNORÉ au lancement.
        $booking = $this->bookingWithNegotiatedProvider(0.10);

        $result = app(CommissionService::class)->calculateForBooking($booking);

        $this->assertSame(10000, $result['total_cents']);
        $this->assertSame(2000, $result['platform_fee_cents'], 'Le taux plateforme unique (20%) doit primer sur le négocié (10%).');
        $this->assertSame(8000, $result['provider_payout_cents']);
        $this->assertSame(0.20, $result['commission_rate']);
    }

    public function test_honors_negotiated_provider_rate_when_flag_enabled(): void
    {
        config([
            'brio.platform_fee_percent' => 20,
            'brio.use_negotiated_commission' => true,
        ]);

        $booking = $this->bookingWithNegotiatedProvider(0.10);

        $result = app(CommissionService::class)->calculateForBooking($booking);

        // Infra préservée : quand on réactivera les taux négociés, le 10% s'applique.
        $this->assertSame(1000, $result['platform_fee_cents']);
        $this->assertSame(9000, $result['provider_payout_cents']);
        $this->assertSame(0.10, $result['commission_rate']);
    }
}
