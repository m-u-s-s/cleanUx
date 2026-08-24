<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionTimeSettlement;
use App\Services\Missions\HourlyExtensionService;
use App\Services\Missions\HourlySettlementService;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** LE RÈGLEMENT DU TEMPS SUPPLÉMENTAIRE — prouvé au centime, sans jamais parler à Stripe. */
class ReglementDuTempsSupplementaireTest extends TestCase
{
    use RefreshDatabase;

    /** LE CAS COURANT, ET LE PLUS IMPORTANT : la mission tient dans son temps, il n'y a rien à réclamer. */
    public function test_une_mission_dans_son_temps_ne_doit_rien(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 175);

        $reglement = app(HourlySettlementService::class)->constater($mission);

        $this->assertSame(MissionTimeSettlement::STATUT_SANS_OBJET, $reglement->status);
        $this->assertSame(0, $reglement->amount_due_cents);
        $this->assertFalse($reglement->estUneCreance());
    }

    /** LE CAS DE RÉFÉRENCE DE LA SPÉCIFICATION. 3 h achetées à 58,50 €/h effectif, 4 h prestées. */
    public function test_le_depassement_est_reclame_au_tarif_majore(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);

        $reglement = app(HourlySettlementService::class)->constater($mission);

        $this->assertSame(MissionTimeSettlement::STATUT_EN_ATTENTE, $reglement->status);
        $this->assertSame(45, $reglement->overtime_minutes);
        $this->assertSame(0, $reglement->extension_minutes);
        $this->assertSame(5704, $reglement->overtime_amount_cents);
        $this->assertSame(5704, $reglement->amount_due_cents);
        $this->assertSame(17550, $reglement->authorized_amount_cents);
    }

    /** UNE PROLONGATION SE RÈGLE AU TARIF NORMAL, sans majoration — c'est toute la différence entre décider et subir. */
    public function test_une_prolongation_est_reclamee_au_tarif_normal(): void
    {
        $mission = $this->missionEnCours(achetees: 180, ecoulees: 100);

        app(HourlyExtensionService::class)->prolonger($mission->booking, 60);

        $this->terminer($mission, prestees: 235);

        $reglement = app(HourlySettlementService::class)->constater($mission->refresh()->load('booking'));

        $this->assertSame(60, $reglement->extension_minutes);
        $this->assertSame(0, $reglement->overtime_minutes, 'Prestée dans les 4 h achetées : aucun dépassement.');
        $this->assertSame(5850, $reglement->extension_amount_cents, 'Une heure au tarif normal.');
        $this->assertSame(5850, $reglement->amount_due_cents);
    }

    /** PROLONGER PUIS DÉBORDER QUAND MÊME — les deux lignes coexistent, chacune à son tarif. */
    public function test_prolongation_et_depassement_coexistent(): void
    {
        $mission = $this->missionEnCours(achetees: 180, ecoulees: 100);

        app(HourlyExtensionService::class)->prolonger($mission->booking, 60);

        $this->terminer($mission, prestees: 300);

        $reglement = app(HourlySettlementService::class)->constater($mission->refresh()->load('booking'));

        $this->assertSame(60, $reglement->extension_minutes);
        $this->assertSame(45, $reglement->overtime_minutes);
        // 58,50 (prolongation) + 57,04 (dépassement majoré)
        $this->assertSame(5850, $reglement->extension_amount_cents);
        $this->assertSame(5704, $reglement->overtime_amount_cents);
        $this->assertSame(11554, $reglement->amount_due_cents);
    }

    /** L'IDEMPOTENCE — la clôture peut être rejouée, et une reprise planifiée passe derrière. */
    public function test_constater_deux_fois_ne_produit_quune_creance(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);

        $service = app(HourlySettlementService::class);
        $premier = $service->constater($mission);
        $second = $service->constater($mission->refresh()->load('booking'));

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, MissionTimeSettlement::query()->count());
        $this->assertSame(5704, $second->amount_due_cents);
    }

    /** UN RÈGLEMENT ENCAISSÉ NE SE RECALCULE PAS. */
    public function test_un_reglement_encaisse_est_intouchable(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);

        $service = app(HourlySettlementService::class);
        $reglement = $service->constater($mission);

        $reglement->forceFill([
            'status' => MissionTimeSettlement::STATUT_ENCAISSE,
            'charged_at' => now(),
            'amount_due_cents' => 5704,
        ])->save();

        config()->set('order_engine.overtime_multiplier', 3.0);

        $relu = $service->constater($mission->refresh()->load('booking'));

        $this->assertSame(5704, $relu->amount_due_cents, 'Le montant figé ne bouge pas.');
        $this->assertSame(MissionTimeSettlement::STATUT_ENCAISSE, $relu->status);
    }

    /** LE PRESTATAIRE EST PAYÉ À SON TARIF NORMAL, la majoration revient à la plateforme. */
    public function test_le_prestataire_ne_touche_pas_la_majoration(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);

        $reglement = app(HourlySettlementService::class)->constater($mission);

        // 45 min au tarif NORMAL : 58,50 × 0,75 = 43,88 €. Le client en paie 57,04.
        $this->assertSame(4388, app(HourlySettlementService::class)->partPrestataireCents($reglement));
        $this->assertSame(5704, $reglement->amount_due_cents);
    }

    /** L'HORLOGE S'ARRÊTE À LA CLÔTURE. */
    public function test_lhorloge_ne_court_plus_apres_la_cloture(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);

        // On avance de deux jours : le constat doit rendre exactement la même chose.
        $this->travel(2)->days();

        $reglement = app(HourlySettlementService::class)->constater($mission->refresh()->load('booking'));

        $this->assertSame(240, $reglement->elapsed_minutes);
        $this->assertSame(45, $reglement->overtime_minutes);
        $this->assertSame(5704, $reglement->amount_due_cents);
    }

    /** Le plafond borne aussi la créance : jamais plus que la durée achetée. */
    public function test_le_plafond_borne_la_creance(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 600);

        $reglement = app(HourlySettlementService::class)->constater($mission);

        $this->assertTrue($reglement->capped);
        $this->assertSame(180, $reglement->overtime_minutes);
        $this->assertSame(22815, $reglement->amount_due_cents);
    }

    /** TÉMOIN : une prestation au forfait ne produit aucun règlement de temps. */
    public function test_un_forfait_ne_produit_aucun_reglement(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 600);
        $mission->booking->resolveTrade()?->forceFill(['hourly_billing' => false])->save();

        $this->assertNull(app(HourlySettlementService::class)->constater($mission->refresh()->load('booking')));
        $this->assertSame(0, MissionTimeSettlement::query()->count());
    }

    // ── La reprise ───────────────────────────────────────────────────────

    public function test_la_commande_de_reprise_compte_les_creances(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);
        app(HourlySettlementService::class)->constater($mission);

        MissionTimeSettlement::query()->update(['updated_at' => now()->subHour()]);

        $this->artisan('temps:reprendre-les-reglements')
            ->expectsOutputToContain('Règlements de temps en souffrance : 1')
            ->assertSuccessful();
    }

    /** Un prélèvement impossible ne doit JAMAIS produire un « encaissé ». */
    public function test_un_prelevement_impossible_laisse_la_creance_ouverte(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);
        $reglement = app(HourlySettlementService::class)->constater($mission);

        app(HourlySettlementService::class)->encaisser($reglement);

        $reglement->refresh();

        $this->assertSame(MissionTimeSettlement::STATUT_ECHOUE, $reglement->status);
        $this->assertNull($reglement->charged_at);
        $this->assertSame(1, $reglement->attempts);
        $this->assertNotNull($reglement->last_error);
    }

    /** TÉMOIN : sans créance, la commande ne fabrique rien. */
    public function test_sans_creance_la_commande_ne_fait_rien(): void
    {
        $this->artisan('temps:reprendre-les-reglements')
            ->expectsOutputToContain('Règlements de temps en souffrance : 0')
            ->assertSuccessful();
    }

    /** Au-delà du plafond de tentatives, l'affaire passe à un humain. */
    public function test_au_dela_du_plafond_le_dossier_passe_a_un_humain(): void
    {
        $mission = $this->missionTerminee(achetees: 180, prestees: 240);
        $reglement = app(HourlySettlementService::class)->constater($mission);

        $reglement->forceFill(['attempts' => 3, 'status' => MissionTimeSettlement::STATUT_ECHOUE])->save();
        MissionTimeSettlement::query()->update(['updated_at' => now()->subHour()]);

        $this->artisan('temps:reprendre-les-reglements --tentatives=3')
            ->expectsOutputToContain('à traiter à la main : 1')
            ->assertSuccessful();
    }

    // ─────────────────────────────────────────────────────────────────────

    private function missionEnCours(int $achetees, int $ecoulees): Mission
    {
        $scenario = SpineScenario::make()->build();

        $scenario->booking->resolveTrade()?->forceFill([
            'hourly_billing' => true,
            'default_hourly_rate' => 45,
        ])->save();

        $scenario->booking->forceFill([
            'purchased_minutes' => $achetees,
            'estimated_duration_minutes' => $achetees,
            'devis_estime' => 175.50,
            'payment_amount_cents' => 17550,
            'currency' => 'EUR',
        ])->save();

        Mission::query()->whereKey($scenario->mission->getKey())->update([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subMinutes($ecoulees),
            'actual_end_at' => null,
        ]);

        return $scenario->mission->refresh()->load('booking');
    }

    private function missionTerminee(int $achetees, int $prestees): Mission
    {
        $mission = $this->missionEnCours($achetees, $prestees);

        return $this->terminer($mission, $prestees);
    }

    private function terminer(Mission $mission, int $prestees): Mission
    {
        $debut = now()->subMinutes($prestees);

        Mission::query()->whereKey($mission->getKey())->update([
            'status' => MissionStatus::COMPLETED,
            'actual_start_at' => $debut,
            'actual_end_at' => $debut->copy()->addMinutes($prestees),
        ]);

        return $mission->refresh()->load('booking');
    }
}
