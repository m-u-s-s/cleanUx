<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\BookingTip;
use App\Models\User;
use App\Services\AccountingV2\Posting\BookingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit MEDIUM — tips & assurances au grand livre (modèle agent) :
 *   - Pourboire = pass-through au prestataire → dette (467), pas de produit.
 *   - Prime d'assurance = pass-through à l'assureur revendu → dette fournisseur (401).
 */
class TipsInsuranceSettlementPostingTest extends TestCase
{
    use RefreshDatabase;

    private function entries(string $sourceType, int $id)
    {
        return AccountingEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $id)
            ->get();
    }

    public function test_tip_posts_a_balanced_provider_liability(): void
    {
        config(['accounting_v2.marketplace.tips_payable_account' => '467']);

        $booking = Booking::factory()->create();
        $tip = BookingTip::create([
            'code' => 'TIP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_user_id' => User::factory()->client()->create()->id,
            'provider_user_id' => User::factory()->employe()->create()->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_CHARGED,
            'charged_at' => now(),
        ]);

        app(BookingPostingService::class)->postTipSettlement($tip);

        $entries = $this->entries('BookingTip', $tip->id);
        $this->assertSame((int) $entries->sum('debit_cents'), (int) $entries->sum('credit_cents'));
        $this->assertSame(1500, (int) $entries->sum('debit_cents'));
        $this->assertSame(1500, (int) $entries->firstWhere('account_code', '467')->credit_cents);
    }

    public function test_insurance_posts_a_balanced_insurer_liability(): void
    {
        config(['accounting_v2.marketplace.insurer_payable_account' => '401']);

        $booking = Booking::factory()->create();
        $insurance = BookingInsurance::create([
            'booking_id' => $booking->id,
            'plan_id' => \App\Models\InsurancePlan::factory()->create()->id,
            'user_id' => User::factory()->client()->create()->id,
            'premium_cents' => 900,
            'coverage_amount_cents' => 500000,
            'currency' => 'EUR',
            'status' => BookingInsurance::STATUS_ACTIVE,
            'external_provider' => 'mock',
        ]);

        app(BookingPostingService::class)->postInsuranceSettlement($insurance);

        $entries = $this->entries('BookingInsurance', $insurance->id);
        $this->assertSame((int) $entries->sum('debit_cents'), (int) $entries->sum('credit_cents'));
        $this->assertSame(900, (int) $entries->sum('debit_cents'));
        $this->assertSame(900, (int) $entries->firstWhere('account_code', '401')->credit_cents);
    }

    public function test_confirm_charge_posts_tip_to_gl_when_auto_post_enabled(): void
    {
        config(['accounting_v2.auto_post_enabled' => true]);

        $booking = Booking::factory()->create();
        $tip = BookingTip::create([
            'code' => 'TIP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_user_id' => User::factory()->client()->create()->id,
            'provider_user_id' => User::factory()->employe()->create()->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_PENDING,
        ]);

        app(\App\Services\Tips\TipService::class)->confirmCharge($tip, 'pi_fake');

        $this->assertTrue($this->entries('BookingTip', $tip->id)->isNotEmpty(), 'Le pourboire chargé doit être posté au GL.');
    }

    public function test_tip_settlement_is_idempotent(): void
    {
        $booking = Booking::factory()->create();
        $tip = BookingTip::create([
            'code' => 'TIP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_user_id' => User::factory()->client()->create()->id,
            'provider_user_id' => User::factory()->employe()->create()->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_CHARGED,
            'charged_at' => now(),
        ]);

        app(BookingPostingService::class)->postTipSettlement($tip);
        app(BookingPostingService::class)->postTipSettlement($tip);

        $this->assertCount(1, $this->entries('BookingTip', $tip->id)->pluck('batch_id')->unique());
    }
}
