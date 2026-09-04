<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\CustomerCredit;
use App\Models\User;
use App\Services\Finance\CustomerCreditApplicationService;
use App\Services\Payments\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UN AVOIR CLIENT EST UN GESTE PARTAGÉ — et le prestataire doit l'apprendre sur son écran.
 *
 * DÉCISION ASSUMÉE : un avoir accordé par la plateforme réduit le prix de la mission, et la part
 * du prestataire suit ce prix réduit. Le geste commercial est donc porté par les deux, au prorata.
 *
 * CE QUI NE SERAIT PAS ACCEPTABLE, c'est qu'il le découvre seul, six mois plus tard, en
 * rapprochant ses relevés. Ces tests fixent les deux moitiés : le partage tel qu'il est, ET le
 * fait qu'il soit dit.
 */
class LAvoirSeDitAuPrestataireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['brio.platform_fee_percent' => 15, 'brio.minimum_commission_cents' => 200]);
    }

    // ── Le partage, tel qu'il est ──────────────────────────────────────────

    /**
     * L'AVOIR RÉDUIT LE PRIX, ET LES DEUX PARTS AVEC.
     *
     * Sur 100 € avec 40 € d'avoir : le client paie 60 par carte, la plateforme garde 9 au lieu
     * de 15, le prestataire reçoit 51 au lieu de 85. Ce test grave ce comportement pour qu'un
     * changement futur soit une DÉCISION, pas un accident.
     */
    public function test_l_avoir_reduit_le_prix_et_les_deux_parts(): void
    {
        $client = User::factory()->client()->create();
        $reservation = Booking::factory()->create([
            'client_id' => $client->id,
            'devis_estime' => 100,
        ]);

        CustomerCredit::create([
            'client_id' => $client->id,
            'type' => 'goodwill',
            'amount' => 40,
            'remaining_amount' => 40,
            'status' => 'active',
        ]);

        app(CustomerCreditApplicationService::class)->applyAvailableCredits($client, $reservation);

        $partage = app(CommissionService::class)->calculateForBooking($reservation->refresh());

        $this->assertSame(6000, $partage['total_cents']);
        $this->assertSame(900, $partage['platform_fee_cents']);
        $this->assertSame(5100, $partage['provider_payout_cents']);
    }

    /** TÉMOIN — sans avoir, les mêmes 100 € donnent 15 et 85. */
    public function test_temoin_sans_avoir_le_partage_est_entier(): void
    {
        $reservation = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'devis_estime' => 100,
        ]);

        $partage = app(CommissionService::class)->calculateForBooking($reservation);

        $this->assertSame(10000, $partage['total_cents']);
        $this->assertSame(1500, $partage['platform_fee_cents']);
        $this->assertSame(8500, $partage['provider_payout_cents']);
    }

    /** L'INSTANTANÉ GARDE LA TRACE : sans elle, l'écran ne pourrait rien dire au prestataire. */
    public function test_l_instantane_garde_la_trace_de_l_avoir(): void
    {
        $client = User::factory()->client()->create();
        $reservation = Booking::factory()->create(['client_id' => $client->id, 'devis_estime' => 100]);

        CustomerCredit::create([
            'client_id' => $client->id, 'type' => 'goodwill',
            'amount' => 40, 'remaining_amount' => 40, 'status' => 'active',
        ]);

        app(CustomerCreditApplicationService::class)->applyAvailableCredits($client, $reservation);

        $instantane = (array) $reservation->refresh()->pricing_snapshot;

        $this->assertEqualsWithDelta(40.0, $instantane['customer_credit_applied'], 0.01);
        $this->assertEqualsWithDelta(60.0, $instantane['devis_after_credit'], 0.01);
    }

    // ── Et il est DIT ──────────────────────────────────────────────────────

    /** LA FICHE DE MISSION L'ANNONCE, avec le montant et la conséquence. */
    public function test_la_fiche_de_mission_annonce_l_avoir(): void
    {
        $client = User::factory()->client()->create();
        $reservation = Booking::factory()->create(['client_id' => $client->id, 'devis_estime' => 100]);

        CustomerCredit::create([
            'client_id' => $client->id, 'type' => 'goodwill',
            'amount' => 40, 'remaining_amount' => 40, 'status' => 'active',
        ]);

        app(CustomerCreditApplicationService::class)->applyAvailableCredits($client, $reservation);

        $rendu = $this->blade(
            '<x-note-avoir-client :booking="$booking" />',
            ['booking' => $reservation->refresh()],
        );

        $rendu->assertSee('avoir', false);
        $rendu->assertSee('prix réduit', false);
    }

    /**
     * TÉMOIN — sans avoir, la note ne s'affiche PAS.
     *
     * Une mention permanente serait du bruit : on cesserait de la lire, et elle manquerait le
     * jour où elle compte.
     */
    public function test_temoin_sans_avoir_la_note_ne_s_affiche_pas(): void
    {
        $reservation = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'devis_estime' => 100,
        ]);

        $this->blade('<x-note-avoir-client :booking="$booking" />', ['booking' => $reservation])
            ->assertDontSee('avoir', false);
    }

    /** LA NOTE NE RECALCULE RIEN : elle lit l'instantané, sinon elle divergerait du versement. */
    public function test_la_note_lit_l_instantane_et_ne_recalcule_pas(): void
    {
        $reservation = Booking::factory()->create(['devis_estime' => 60]);

        // Un instantané volontairement incohérent avec `devis_estime` : la note doit afficher
        // CE QUI A ÉTÉ APPLIQUÉ, pas ce qu'un recalcul donnerait.
        $reservation->forceFill([
            'pricing_snapshot' => ['customer_credit_applied' => 40.0, 'devis_after_credit' => 60.0],
        ])->save();

        $this->blade('<x-note-avoir-client :booking="$booking" />', ['booking' => $reservation])
            ->assertSee('40', false);
    }
}
