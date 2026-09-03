<?php

namespace Tests\Feature\Commission;

use App\Models\CommissionRule;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Services\Commission\ContexteDeCommission;
use App\Services\Commission\ResolveurDeCommission;
use App\Services\Payments\CommissionService;
use App\Services\PeerRental\PeerPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES COMMISSIONS SE RÈGLENT SANS RIEN CASSER.
 *
 * Cinq taux vivaient en dur dans `config/`. Le socle les rend réglables — mais la première chose
 * qu'il doit prouver, c'est qu'il ne change RIEN tant que rien n'est réglé : sinon le poser
 * modifierait en silence le prix de chaque mission déjà en cours.
 *
 * Chaque règle porte donc son témoin de non-régression.
 */
class LesCommissionsSeReglentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // LES TAUX DE REPLI SONT POSES ICI, pour tous les cas : l'environnement de test peut
        // porter d'autres valeurs, et un temoin de non-regression qui mesure un chiffre
        // inconnu ne prouve rien.
        config([
            'brio.platform_fee_percent' => 15,
            'brio.minimum_commission_cents' => 200,
            'peer_rental.commission_percent' => 25,
        ]);

        app(ResolveurDeCommission::class)->oublierLeCache();
    }

    // ── Le repli : rien ne bouge tant que rien n'est réglé ─────────────────

    /** SANS AUCUNE RÈGLE, le taux plateforme est exactement celui d'avant. */
    public function test_sans_regle_le_taux_de_la_plateforme_ne_bouge_pas(): void
    {
        $partage = app(CommissionService::class)->calculateForAmount(
            10000, null, 'eur', null, ContexteDeCommission::prestation(),
        );

        $this->assertSame(1500, $partage['platform_fee_cents']);
        $this->assertSame(8500, $partage['provider_payout_cents']);
        $this->assertNull($partage['commission_rule_id']);
    }

    /** SANS AUCUNE RÈGLE, la location entre membres garde son propre taux. */
    public function test_sans_regle_la_location_entre_membres_garde_ses_vingt_cinq_pour_cent(): void
    {
        $this->assertEqualsWithDelta(0.25, app(PeerPricing::class)->tauxDeCommission('vehicle'), 0.0001);
    }

    /** SANS CONTEXTE DU TOUT, l'ancien appel se comporte à l'identique. */
    public function test_un_appel_sans_contexte_se_comporte_comme_avant(): void
    {
        CommissionRule::create(['label' => 'Course', 'module' => 'prestation', 'percent' => 8]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $partage = app(CommissionService::class)->calculateForAmount(10000);

        // AUCUN CONTEXTE, AUCUNE RÈGLE APPLIQUÉE : un appelant qui ne dit pas d'où vient
        // l'argent ne doit pas hériter du taux d'un module au hasard.
        $this->assertSame(1500, $partage['platform_fee_cents']);
    }

    // ── Le réglage ─────────────────────────────────────────────────────────

    /** « Course 8 % » — le cas le plus simple, et celui qui manquait. */
    public function test_un_taux_par_metier_s_applique(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);

        CommissionRule::create([
            'label' => 'Course à 8 %', 'module' => 'prestation',
            'trade_id' => $course->id, 'percent' => 8,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $partage = app(CommissionService::class)->calculateForAmount(
            10000, null, 'eur', null, ContexteDeCommission::prestation($course->id),
        );

        $this->assertSame(800, $partage['platform_fee_cents']);
        $this->assertSame(9200, $partage['provider_payout_cents']);
    }

    /** TÉMOIN — un autre métier garde le taux général. */
    public function test_temoin_un_autre_metier_garde_le_taux_general(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);
        $depannage = Trade::factory()->create(['name' => 'Dépannage']);

        CommissionRule::create([
            'label' => 'Course à 8 %', 'module' => 'prestation',
            'trade_id' => $course->id, 'percent' => 8,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $partage = app(CommissionService::class)->calculateForAmount(
            10000, null, 'eur', null, ContexteDeCommission::prestation($depannage->id),
        );

        $this->assertSame(1500, $partage['platform_fee_cents']);
    }

    /**
     * GRATUIT VEUT DIRE GRATUIT.
     *
     * Un taux à 0 % avec le plancher de 2 € prélèverait quand même deux euros : la règle porte
     * son propre plancher, sinon « gratuit » n'existerait pas.
     */
    public function test_zero_pour_cent_ne_preleve_rien(): void
    {
        CommissionRule::create([
            'label' => 'Lancement gratuit', 'module' => 'prestation',
            'percent' => 0, 'min_cents' => 0,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $partage = app(CommissionService::class)->calculateForAmount(
            10000, null, 'eur', null, ContexteDeCommission::prestation(),
        );

        $this->assertSame(0, $partage['platform_fee_cents']);
        $this->assertSame(10000, $partage['provider_payout_cents']);
    }

    /** ET CENT POUR CENT AUSSI : c'est une décision, pas une erreur de saisie. */
    public function test_cent_pour_cent_prend_tout(): void
    {
        CommissionRule::create(['label' => 'Tout', 'module' => 'prestation', 'percent' => 100]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $partage = app(CommissionService::class)->calculateForAmount(
            10000, null, 'eur', null, ContexteDeCommission::prestation(),
        );

        $this->assertSame(10000, $partage['platform_fee_cents']);
        $this->assertSame(0, $partage['provider_payout_cents']);
    }

    // ── L'ordre de précision ───────────────────────────────────────────────

    /**
     * LA PLUS PRÉCISE GAGNE.
     *
     * Sans cet ordre, poser un taux de zone effacerait par accident un taux de métier — ou
     * l'inverse, selon l'ordre d'insertion. C'est le défaut le plus coûteux d'un tel système.
     */
    public function test_la_regle_la_plus_precise_gagne(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);
        $zone = ServiceZone::factory()->create(['name' => 'Bruxelles']);

        CommissionRule::create(['label' => 'Général', 'module' => 'prestation', 'percent' => 15]);
        CommissionRule::create([
            'label' => 'Course', 'module' => 'prestation',
            'trade_id' => $course->id, 'percent' => 8,
        ]);
        CommissionRule::create([
            'label' => 'Course à Bruxelles', 'module' => 'prestation',
            'trade_id' => $course->id, 'service_zone_id' => $zone->id, 'percent' => 5,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $taux = app(ResolveurDeCommission::class)->pour(
            ContexteDeCommission::prestation($course->id, $zone->id),
        );

        $this->assertEqualsWithDelta(0.05, $taux->taux, 0.0001);
        $this->assertSame('Course à Bruxelles', $taux->origine);
    }

    /** TÉMOIN — hors de la zone, c'est la règle du métier qui reprend la main. */
    public function test_temoin_hors_de_la_zone_le_taux_du_metier_reprend(): void
    {
        $course = Trade::factory()->create(['name' => 'Course']);
        $zone = ServiceZone::factory()->create(['name' => 'Bruxelles']);
        $ailleurs = ServiceZone::factory()->create(['name' => 'Liège']);

        CommissionRule::create(['label' => 'Course', 'module' => 'prestation', 'trade_id' => $course->id, 'percent' => 8]);
        CommissionRule::create([
            'label' => 'Course à Bruxelles', 'module' => 'prestation',
            'trade_id' => $course->id, 'service_zone_id' => $zone->id, 'percent' => 5,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $taux = app(ResolveurDeCommission::class)->pour(
            ContexteDeCommission::prestation($course->id, $ailleurs->id),
        );

        $this->assertEqualsWithDelta(0.08, $taux->taux, 0.0001);
    }

    /** À PRÉCISION ÉGALE, LA PRIORITÉ TRANCHE — jamais l'ordre d'insertion. */
    public function test_a_precision_egale_la_priorite_tranche(): void
    {
        CommissionRule::create(['label' => 'Basse', 'module' => 'prestation', 'percent' => 15, 'priority' => 0]);
        CommissionRule::create(['label' => 'Haute', 'module' => 'prestation', 'percent' => 9, 'priority' => 10]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $taux = app(ResolveurDeCommission::class)->pour(ContexteDeCommission::prestation());

        $this->assertSame('Haute', $taux->origine);
    }

    // ── La durée : « 20 %, puis 5 % après deux semaines » ──────────────────

    public function test_un_taux_degressif_apres_deux_semaines(): void
    {
        CommissionRule::create([
            'label' => 'Voiture', 'module' => 'peer_rental', 'asset_type' => 'vehicle', 'percent' => 20,
        ]);
        CommissionRule::create([
            'label' => 'Voiture longue durée', 'module' => 'peer_rental', 'asset_type' => 'vehicle',
            'min_duration_days' => 14, 'percent' => 5,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $resolveur = app(ResolveurDeCommission::class);

        $courte = $resolveur->pour(ContexteDeCommission::locationEntreMembres('vehicle', 3));
        $longue = $resolveur->pour(ContexteDeCommission::locationEntreMembres('vehicle', 20));

        $this->assertEqualsWithDelta(0.20, $courte->taux, 0.0001);
        $this->assertEqualsWithDelta(0.05, $longue->taux, 0.0001);
    }

    /** LE SEUIL EST UN SEUIL : le quatorzième jour bascule déjà. */
    public function test_le_seuil_bascule_des_le_jour_dit(): void
    {
        CommissionRule::create(['label' => 'Court', 'module' => 'peer_rental', 'asset_type' => 'vehicle', 'percent' => 20]);
        CommissionRule::create([
            'label' => 'Long', 'module' => 'peer_rental', 'asset_type' => 'vehicle',
            'min_duration_days' => 14, 'percent' => 5,
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $resolveur = app(ResolveurDeCommission::class);

        $this->assertEqualsWithDelta(0.20, $resolveur->pour(ContexteDeCommission::locationEntreMembres('vehicle', 13))->taux, 0.0001);
        $this->assertEqualsWithDelta(0.05, $resolveur->pour(ContexteDeCommission::locationEntreMembres('vehicle', 14))->taux, 0.0001);
    }

    // ── La saison ──────────────────────────────────────────────────────────

    /** UNE RÈGLE DATÉE N'EXISTE PAS HORS DE SA FENÊTRE. */
    public function test_une_regle_hors_saison_ne_s_applique_pas(): void
    {
        CommissionRule::create([
            'label' => 'Janvier gratuit', 'module' => 'prestation', 'percent' => 0, 'min_cents' => 0,
            'starts_on' => now()->addMonth()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $taux = app(ResolveurDeCommission::class)->pour(ContexteDeCommission::prestation());

        $this->assertNull($taux->regle);
    }

    /** TÉMOIN — dans sa fenêtre, la même règle s'applique. */
    public function test_temoin_dans_sa_fenetre_la_regle_s_applique(): void
    {
        CommissionRule::create([
            'label' => 'Janvier gratuit', 'module' => 'prestation', 'percent' => 0, 'min_cents' => 0,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $this->assertSame('Janvier gratuit', app(ResolveurDeCommission::class)
            ->pour(ContexteDeCommission::prestation())->origine);
    }

    /** UNE RÈGLE DÉSACTIVÉE N'EXISTE PAS NON PLUS. */
    public function test_une_regle_desactivee_est_ignoree(): void
    {
        CommissionRule::create(['label' => 'Off', 'module' => 'prestation', 'percent' => 3, 'is_active' => false]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $this->assertNull(app(ResolveurDeCommission::class)->pour(ContexteDeCommission::prestation())->regle);
    }

    // ── La note affichée ───────────────────────────────────────────────────

    /** LA NOTE DIT LE CHIFFRE ET SA RAISON : un pourcentage sans origine ne se conteste pas. */
    public function test_la_note_dit_le_taux_et_sa_raison(): void
    {
        CommissionRule::create(['label' => 'Course à 8 %', 'module' => 'prestation', 'percent' => 8]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $note = app(ResolveurDeCommission::class)->pour(ContexteDeCommission::prestation())->note();

        $this->assertStringContainsString('8 %', $note);
        $this->assertStringContainsString('Course à 8 %', $note);
    }

    /** ET « GRATUIT » SE DIT AUTREMENT QUE « 0 % » : ce n'est pas la même nouvelle. */
    public function test_la_note_annonce_la_gratuite(): void
    {
        CommissionRule::create(['label' => 'Lancement', 'module' => 'prestation', 'percent' => 0, 'min_cents' => 0]);
        app(ResolveurDeCommission::class)->oublierLeCache();

        $this->assertStringContainsString(
            'Aucune commission',
            app(ResolveurDeCommission::class)->pour(ContexteDeCommission::prestation())->note(),
        );
    }
}
