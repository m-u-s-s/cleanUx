<?php

namespace Tests\Feature\Finance;

use App\Models\Booking;
use App\Models\Country;
use App\Models\CountryBillingProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Finance\FinanceDocumentCalculator;
use App\Services\Finance\TaxeDeLaCommande;
use App\Services\Payments\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE PRIX DU SERVICE EST HORS TAXE, ET LA TVA S'Y AJOUTE À LA DEMANDE.
 *
 * Le taux est celui que le super-administrateur ou le comptable règle pour le pays, et le lieu de
 * l'intervention désigne ce pays. Avant, personne ne les mettait d'accord : la page de
 * confirmation ne montrait aucune TVA, Stripe encaissait le HT, la facture réclamait le TTC — le
 * client payait 100 € contre une facture de 121 €, et les 21 € restaient dus à vie.
 */
class LePrixEstHorsTaxeEtLaTvaSAjouteTest extends TestCase
{
    use RefreshDatabase;

    private function pays(string $iso, float $taux): Country
    {
        $pays = Country::query()->firstOrCreate(
            ['iso_code' => $iso],
            ['name' => $iso, 'currency_code' => 'EUR', 'is_active' => true],
        );

        CountryBillingProfile::query()->updateOrCreate(
            ['country_id' => $pays->id],
            ['default_tax_rate' => $taux],
        );

        return $pays;
    }

    private function reservation(float $htEuros, ?Country $pays = null, ?float $tauxGele = null): Booking
    {
        // Supplement de deplacement a zero : ce test mesure la TVA, pas la grille de zone.
        // La fabrique en tire un au hasard, et la facture l'ajoute legitimement au sous-total.
        $zone = $pays
            ? ServiceZone::factory()->create(['country_id' => $pays->id, 'travel_surcharge' => 0])
            : null;

        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'client_id' => User::factory()->client()->create()->id,
            'service_zone_id' => $zone?->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'devis_estime' => $htEuros,
            'pricing_snapshot' => $tauxGele === null ? [] : ['tax_rate' => $tauxGele],
        ]);
    }

    #[Test]
    public function la_tva_s_ajoute_au_prix_hors_taxe(): void
    {
        $taxe = app(TaxeDeLaCommande::class)->decoupage(10000, 21.0);

        $this->assertSame(10000, $taxe['ht_cents']);
        $this->assertSame(2100, $taxe['tva_cents']);
        $this->assertSame(12100, $taxe['ttc_cents']);
    }

    /** HT + TVA doit faire EXACTEMENT le TTC, au centime, quel que soit l'arrondi. */
    #[Test]
    public function le_decoupage_tombe_juste_au_centime(): void
    {
        foreach ([1, 7, 33, 99, 1234, 99999] as $ht) {
            foreach ([0.0, 6.0, 20.0, 21.0, 5.5] as $taux) {
                $taxe = app(TaxeDeLaCommande::class)->decoupage($ht, $taux);

                $this->assertSame(
                    $taxe['ttc_cents'],
                    $taxe['ht_cents'] + $taxe['tva_cents'],
                    "HT {$ht} au taux {$taux} : la somme ne retombe pas sur le TTC",
                );
            }
        }
    }

    #[Test]
    public function le_taux_suit_le_pays_de_la_zone(): void
    {
        $maroc = $this->pays('MA', 20.0);

        $taux = app(TaxeDeLaCommande::class)->tauxPourLaReservation($this->reservation(100.0, $maroc));

        $this->assertSame(20.0, $taux, 'le taux du pays de la zone, pas le 21 % par défaut');
    }

    /** LE TAUX SE FIGE AU DEVIS : changer le réglage ne rouvre pas une facture déjà émise. */
    #[Test]
    public function le_taux_gele_l_emporte_sur_le_reglage_du_jour(): void
    {
        $belgique = $this->pays('BE', 21.0);
        $reservation = $this->reservation(100.0, $belgique, tauxGele: 6.0);

        $this->assertSame(6.0, app(TaxeDeLaCommande::class)->tauxPourLaReservation($reservation));
    }

    /**
     * LE CŒUR : ce que Stripe encaisse, ce que Stripe retient, ce que le prestataire touche.
     *
     * La TVA revient à la plateforme, qui la reverse : elle entre dans la retenue Stripe et
     * LAISSE INTACTE la part du prestataire.
     */
    #[Test]
    public function le_client_paie_le_ttc_et_le_prestataire_touche_toujours_le_ht_moins_commission(): void
    {
        $reservation = $this->reservation(100.0, $this->pays('BE', 21.0));

        $partage = app(CommissionService::class)->calculateForBooking($reservation);

        $this->assertSame(10000, $partage['total_cents'], 'le partage porte sur le HT');
        $this->assertSame(2100, $partage['tax_cents']);
        $this->assertSame(12100, $partage['charge_cents'], 'le client paie le TTC');

        $this->assertSame(
            $partage['platform_fee_cents'] + 2100,
            $partage['platform_fee_with_tax_cents'],
            'Stripe retient la commission PLUS la TVA',
        );

        $this->assertSame(
            10000 - $partage['platform_fee_cents'],
            $partage['provider_payout_cents'],
            'la part du prestataire ne bouge pas d’un centime avec la TVA',
        );
    }

    /** LE TÉMOIN : à 0 %, le TTC vaut le HT et rien ne change pour personne. */
    #[Test]
    public function a_taux_nul_le_ttc_vaut_le_ht(): void
    {
        $reservation = $this->reservation(100.0, $this->pays('XX', 0.0));

        $partage = app(CommissionService::class)->calculateForBooking($reservation);

        $this->assertSame(0, $partage['tax_cents']);
        $this->assertSame($partage['total_cents'], $partage['charge_cents']);
        $this->assertSame($partage['platform_fee_cents'], $partage['platform_fee_with_tax_cents']);
    }

    /** La facture réclame exactement ce qui a été encaissé — c'est tout l'objet du gel. */
    #[Test]
    public function la_facture_reclame_ce_que_stripe_a_encaisse(): void
    {
        $reservation = $this->reservation(100.0, $this->pays('BE', 21.0));
        $partage = app(CommissionService::class)->calculateForBooking($reservation);

        $reservation->forceFill(['pricing_snapshot' => ['tax_rate' => $partage['tax_rate']]])->save();

        $facture = app(FinanceDocumentCalculator::class)->amountBreakdownFor($reservation->fresh());

        $this->assertSame(
            $partage['charge_cents'],
            (int) round(((float) $facture['total_amount']) * 100),
            'le total de la facture doit valoir le montant encaissé, au centime',
        );
    }
}
