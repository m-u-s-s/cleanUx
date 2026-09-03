<?php

namespace Tests\Feature\PeerRental;

use App\Models\PeerStay;
use App\Models\PeerVehicle;
use App\Services\PeerRental\PeerAvailability;
use App\Services\PeerRental\PeerPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LA COUCHE PARTAGEE IGNORE CE QU'ELLE LOUE.
 *
 * Disponibilite et tarification ne connaissent plus que `Louable`. Chaque bien declare ce qu'il
 * facture en plus — une livraison pour une voiture, un menage et des voyageurs pour un logement —
 * et la commission suit le type, parce que le risque d'un logement n'est pas celui d'une voiture.
 */
class LesServicesPartagesAcceptentUnLogementTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_disponibilite_repond_pour_un_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['min_nights' => 2]);

        $this->assertTrue(app(PeerAvailability::class)->estLibre(
            $logement,
            Carbon::now()->addDays(10),
            Carbon::now()->addDays(13),
        ));
    }

    /** UN BROUILLON NE SE RESERVE PAS, et l'ecran doit pouvoir dire pourquoi. */
    public function test_un_logement_non_publie_donne_son_motif(): void
    {
        $logement = PeerStay::factory()->create();

        $motif = app(PeerAvailability::class)->motifDIndisponibilite(
            $logement,
            Carbon::now()->addDays(10),
            Carbon::now()->addDays(13),
        );

        $this->assertNotNull($motif);
        $this->assertStringContainsString('n’est pas proposé', (string) $motif);
    }

    /** LA DUREE PLANCHER DU LOGEMENT EST RESPECTEE, et nommee. */
    public function test_un_sejour_trop_court_est_refuse_avec_son_motif(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['min_nights' => 3]);

        $motif = app(PeerAvailability::class)->motifDIndisponibilite(
            $logement,
            Carbon::now()->addDays(10),
            Carbon::now()->addDays(11),
        );

        $this->assertStringContainsString('minimale', (string) $motif);
    }

    /** UNE PERIODE FERMEE PAR LE PROPRIETAIRE BLOQUE, logement compris. */
    public function test_une_periode_fermee_bloque_un_logement(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['min_nights' => 1]);

        $logement->indisponibilites()->create([
            'starts_on' => Carbon::now()->addDays(10)->toDateString(),
            'ends_on' => Carbon::now()->addDays(15)->toDateString(),
            'kind' => 'blocked',
        ]);

        $this->assertFalse(app(PeerAvailability::class)->estLibre(
            $logement,
            Carbon::now()->addDays(11),
            Carbon::now()->addDays(13),
        ));
    }

    /** TEMOIN — hors de la periode fermee, le meme logement reste libre. */
    public function test_temoin_hors_periode_fermee_le_logement_reste_libre(): void
    {
        $logement = PeerStay::factory()->publiee()->create(['min_nights' => 1]);

        $logement->indisponibilites()->create([
            'starts_on' => Carbon::now()->addDays(10)->toDateString(),
            'ends_on' => Carbon::now()->addDays(15)->toDateString(),
            'kind' => 'blocked',
        ]);

        $this->assertTrue(app(PeerAvailability::class)->estLibre(
            $logement,
            Carbon::now()->addDays(20),
            Carbon::now()->addDays(22),
        ));
    }

    /**
     * LE MENAGE SE FACTURE UNE FOIS, PAS PAR NUIT.
     *
     * L'inverse rendrait un long sejour absurdement cher, et c'est la regle de toutes les
     * plateformes du secteur.
     */
    public function test_le_menage_ne_se_facture_qu_une_fois(): void
    {
        $logement = PeerStay::factory()->publiee()->create([
            'nightly_price_cents' => 10000,
            'cleaning_fee_cents' => 5000,
            'guests_included' => 2,
            'extra_guest_price_cents' => 0,
            'discount_3_days_percent' => 0,
            'discount_7_days_percent' => 0,
            'discount_28_days_percent' => 0,
            'pricing_rules' => ['weekend_multiplier' => 1, 'high_season_multiplier' => 1, 'high_season_months' => []],
        ]);

        $court = app(PeerPricing::class)->devis($logement, Carbon::parse('2027-03-01'), Carbon::parse('2027-03-03'));
        $long = app(PeerPricing::class)->devis($logement, Carbon::parse('2027-03-01'), Carbon::parse('2027-03-11'));

        $this->assertSame(5000, $court['supplements']['menage']);
        $this->assertSame(5000, $long['supplements']['menage'], 'Le ménage a été facturé par nuit.');
    }

    /** LE SUPPLEMENT VOYAGEURS, LUI, SUIT LE NOMBRE DE NUITS. */
    public function test_le_supplement_voyageurs_suit_les_nuits(): void
    {
        $logement = PeerStay::factory()->publiee()->create([
            'nightly_price_cents' => 10000,
            'cleaning_fee_cents' => 0,
            'guests_included' => 2,
            'extra_guest_price_cents' => 1000,
            'pricing_rules' => ['weekend_multiplier' => 1, 'high_season_multiplier' => 1, 'high_season_months' => []],
        ]);

        $devis = app(PeerPricing::class)->devis(
            $logement,
            Carbon::parse('2027-03-01'),
            Carbon::parse('2027-03-04'),
            ['voyageurs' => 4],
        );

        // Deux voyageurs en plus, trois nuits : 2 x 1000 x 3.
        $this->assertSame(6000, $devis['supplements']['voyageurs']);
    }

    /** UN LOGEMENT N'A PAS DE KILOMETRAGE : zero s'y lit « sans objet ». */
    public function test_un_logement_ne_porte_aucun_kilometrage(): void
    {
        $devis = app(PeerPricing::class)->devis(
            PeerStay::factory()->publiee()->create(),
            Carbon::parse('2027-03-01'),
            Carbon::parse('2027-03-03'),
        );

        $this->assertSame(0, $devis['included_km']);
    }

    /**
     * LA COMMISSION SUIT LE TYPE DE BIEN.
     *
     * Le risque d'un logement n'est pas celui d'une voiture. Sans reglage propre, le taux general
     * s'applique — aucune decision n'est forcee.
     */
    public function test_la_commission_peut_differer_par_type_de_bien(): void
    {
        config()->set('peer_rental.commission_percent', 25);
        config()->set('peer_rental.commission_percent_par_type', ['stay' => 12, 'vehicle' => null]);

        $prix = app(PeerPricing::class);

        $this->assertSame(0.12, $prix->tauxDeCommission('stay'));
        $this->assertSame(0.25, $prix->tauxDeCommission('vehicle'), 'Sans réglage propre, le taux général s’applique.');
    }

    /** TEMOIN — sans aucun reglage par type, les deux biens prennent le meme taux. */
    public function test_temoin_sans_reglage_les_deux_biens_prennent_le_meme_taux(): void
    {
        config()->set('peer_rental.commission_percent', 25);
        config()->set('peer_rental.commission_percent_par_type', []);

        $prix = app(PeerPricing::class);

        $this->assertSame($prix->tauxDeCommission('vehicle'), $prix->tauxDeCommission('stay'));
    }

    /** LE DEVIS D'UN LOGEMENT FIGE SON TAUX, comme celui d'un vehicule. */
    public function test_le_devis_fige_le_taux_de_commission(): void
    {
        config()->set('peer_rental.commission_percent_par_type', ['stay' => 12]);

        $devis = app(PeerPricing::class)->devis(
            PeerStay::factory()->publiee()->create(),
            Carbon::parse('2027-03-01'),
            Carbon::parse('2027-03-03'),
        );

        $this->assertSame(0.12, $devis['commission_rate']);
        $this->assertGreaterThan(0, $devis['platform_fee_cents']);
        $this->assertGreaterThan(0, $devis['owner_payout_cents']);
    }

    /** TEMOIN — le vehicule garde ses lignes a lui : la generalisation n'a rien emporte. */
    public function test_temoin_le_vehicule_garde_sa_livraison_et_son_kilometrage(): void
    {
        $vehicule = PeerVehicle::factory()->create([
            'status' => PeerVehicle::STATUT_PUBLIE,
            'delivery_enabled' => true,
            'delivery_price_cents' => 2500,
            'included_km_per_day' => 200,
        ]);

        $devis = app(PeerPricing::class)->devis(
            $vehicule,
            Carbon::parse('2027-03-01'),
            Carbon::parse('2027-03-03'),
            ['livraison' => true],
        );

        $this->assertSame(2500, $devis['delivery_cents']);
        $this->assertSame(400, $devis['included_km']);
    }
}
