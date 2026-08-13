<?php

namespace Tests\Feature\Payments;

use App\Models\ProviderProfile;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Services\Payments\ExpressPayoutService;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Le virement express — les frais doivent EXISTER dans les comptes.
 *
 * `demander()` calculait des frais, les rangeait dans les métadonnées de la ligne de versement,
 * puis demandait le retrait du montant BRUT. Les frais n'étaient donc écrits nulle part : ni
 * ligne de portefeuille, ni recette. Le prestataire recevait le brut, la plateforme ne percevait
 * rien, et l'écran continuait d'annoncer un net inférieur.
 */
class ExpressPayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $prestataire;

    private ExpressPayoutService $express;

    private ProviderWalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prestataire = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $this->prestataire->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'stripe_connect_account_id' => 'acct_test_express',
            'stripe_connect_status' => 'active',
        ]);

        $this->wallet = app(ProviderWalletService::class);
        $this->express = app(ExpressPayoutService::class);
    }

    private function crediter(float $montant): void
    {
        ProviderWalletTransaction::create([
            'provider_user_id' => $this->prestataire->id,
            'type' => ProviderWalletTransaction::TYPE_EARNING,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => $montant,
            'currency' => 'EUR',
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'idempotency_key' => 'test:credit:'.uniqid(),
            'occurred_at' => now(),
        ]);
    }

    /** Sur 200 €, 1,5 % font 3,00 € de frais et 197,00 € de net. */
    public function test_le_devis_annonce_frais_et_net(): void
    {
        $devis = $this->express->devis(20000);

        $this->assertSame(300, $devis['fee_cents']);
        $this->assertSame(19700, $devis['net_cents']);
        $this->assertTrue($devis['eligible']);
    }

    /**
     * SOUS LE SEUIL, LE PLANCHER L'EMPORTE.
     *
     * Sur 50 €, 1,5 % ne font que 0,75 € — en dessous des 1,00 € de `FRAIS_MINIMUM_CENTS`, qui
     * s'applique alors. Le distinguer du régime au pourcentage évite de croire à une erreur
     * d'arrondi en lisant une ligne de frais.
     */
    public function test_sous_le_seuil_le_plancher_de_frais_s_applique(): void
    {
        $devis = $this->express->devis(5000);

        $this->assertSame(100, $devis['fee_cents']);
        $this->assertSame(4900, $devis['net_cents']);
    }

    /**
     * LE VERSEMENT PORTE LE NET, pas le brut : c'est le montant qui part réellement.
     */
    public function test_le_versement_porte_le_montant_net(): void
    {
        $this->crediter(200.0);

        $payout = $this->express->demander($this->prestataire, 20000);

        $this->assertEqualsWithDelta(
            197.0,
            (float) $payout->amount,
            0.01,
            'Le versement doit porter le NET : c’est ce que le prestataire reçoit.',
        );
    }

    /**
     * LES FRAIS EXISTENT COMME ÉCRITURE, et pas seulement comme métadonnée.
     *
     * Une métadonnée ne se totalise pas, ne se rapproche pas d'un relevé et n'apparaît dans aucun
     * export comptable. Tant que les frais n'y étaient pas, la plateforme prélevait une somme que
     * ses propres comptes ignoraient.
     */
    public function test_les_frais_sont_une_ecriture_de_portefeuille(): void
    {
        $this->crediter(200.0);

        $payout = $this->express->demander($this->prestataire, 20000);

        $frais = ProviderWalletTransaction::query()
            ->where('provider_user_id', $this->prestataire->id)
            ->where('type', ProviderWalletTransaction::TYPE_PLATFORM_FEE)
            ->where('direction', ProviderWalletTransaction::DIRECTION_DEBIT)
            ->first();

        $this->assertNotNull($frais, 'Les frais express doivent donner lieu à une écriture.');
        $this->assertEqualsWithDelta(3.0, (float) $frais->amount, 0.01);
        $this->assertSame('provider_payout', $frais->source_type);
        $this->assertSame($payout->id, (int) $frais->source_id);
    }

    /** Le solde baisse du BRUT : le net qui part, plus les frais qui restent à la plateforme. */
    public function test_le_solde_baisse_du_montant_brut(): void
    {
        $this->crediter(200.0);

        $this->express->demander($this->prestataire, 20000);

        $this->assertEqualsWithDelta(
            0.0,
            $this->wallet->balance($this->prestataire->id)['available'],
            0.01,
            'Net + frais doivent épuiser exactement les 200 € engagés.',
        );
    }

    /** Deux demandes identiques n'écrivent pas deux fois les mêmes frais. */
    public function test_les_frais_ne_sont_ecrits_qu_une_fois_par_versement(): void
    {
        $this->crediter(200.0);

        $this->express->demander($this->prestataire, 5000);
        $this->express->demander($this->prestataire, 5000);

        $this->assertSame(
            2,
            ProviderWalletTransaction::query()
                ->where('type', ProviderWalletTransaction::TYPE_PLATFORM_FEE)
                ->count(),
            'Un versement, une ligne de frais — ni plus, ni moins.',
        );
    }

    public function test_sous_le_minimum_la_demande_est_refusee(): void
    {
        $this->crediter(50.0);

        $this->expectException(ValidationException::class);
        $this->express->demander($this->prestataire, 1500);
    }

    /** Le solde est vérifié sur le BRUT : demander plus que ce qu'on a reste refusé. */
    public function test_un_solde_insuffisant_est_refuse(): void
    {
        $this->crediter(30.0);

        $this->expectException(ValidationException::class);
        $this->express->demander($this->prestataire, 5000);
    }
}
