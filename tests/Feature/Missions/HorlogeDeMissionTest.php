<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Services\Missions\HourlyMissionClock;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** L'HORLOGE DE DÉPASSEMENT — de l'argent, donc prouvé au centime. */
class HorlogeDeMissionTest extends TestCase
{
    use RefreshDatabase;

    /** LE CAS DE RÉFÉRENCE, celui de la spécification. */
    public function test_le_depassement_empile_le_multiplicateur_sur_le_tarif_deja_majore(): void
    {
        $scenario = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 240);

        $etat = app(HourlyMissionClock::class)->etat($scenario);

        $this->assertTrue($etat['applies']);
        $this->assertSame(5850, $etat['effective_hourly_rate_cents'], 'Le tarif effectif = montant ÷ heures achetées.');
        $this->assertSame(60, $etat['overrun_minutes']);
        $this->assertSame(45, $etat['billable_overtime_minutes'], 'La franchise se DÉDUIT du dépassement.');
        $this->assertSame(5704, $etat['overtime_amount_cents']);
    }

    /** LA FRANCHISE PROTÈGE LE PRESTATAIRE QUI RANGE SES AFFAIRES. */
    public function test_sous_la_franchise_rien_nest_facture(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 192);

        $etat = app(HourlyMissionClock::class)->etat($mission);

        $this->assertSame(12, $etat['overrun_minutes']);
        $this->assertSame(0, $etat['billable_overtime_minutes']);
        $this->assertSame(0, $etat['overtime_amount_cents']);
    }

    /** Une minute au-delà de la franchise déclenche le premier quart d'heure entamé, pas plus. */
    public function test_au_dela_de_la_franchise_on_facture_le_quart_dheure_entame(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 196);

        $etat = app(HourlyMissionClock::class)->etat($mission);

        $this->assertSame(16, $etat['overrun_minutes']);
        $this->assertSame(15, $etat['billable_overtime_minutes'], 'Une minute au-delà de la franchise = un quart d’heure.');
    }

    /** LE PLAFOND BORNE L'ABUS — et il est appliqué APRÈS l'arrondi. */
    public function test_le_plafond_arrete_le_compteur(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 600);

        $etat = app(HourlyMissionClock::class)->etat($mission);

        $this->assertSame(180, $etat['billable_overtime_minutes'], 'Jamais plus que la durée achetée.');
        $this->assertTrue($etat['capped']);
        // 58,50 € × 3 h × 1,30
        $this->assertSame(22815, $etat['overtime_amount_cents']);
    }

    /** LE PIÈGE D'ORDRE : plafonner avant d'arrondir laisserait l'arrondi repasser au-dessus. */
    public function test_larrondi_ne_franchit_pas_le_plafond(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 370);

        $this->assertSame(180, app(HourlyMissionClock::class)->etat($mission)['billable_overtime_minutes']);
    }

    /** Avant l'échéance, le temps restant est POSITIF : c'est ce que le client voit décompter. */
    public function test_avant_lecheance_le_temps_restant_est_positif(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 100);

        $etat = app(HourlyMissionClock::class)->etat($mission);

        $this->assertSame(80, $etat['remaining_minutes']);
        $this->assertSame(0, $etat['overrun_minutes']);
        $this->assertSame(0, $etat['overtime_amount_cents']);
    }

    /** TÉMOIN — sans cette assertion, tous les tests ci-dessus passeraient au vert en mesurant une horloge qui ne s'allume jamais. */
    public function test_une_mission_qui_nest_pas_vendue_au_temps_na_pas_dhorloge(): void
    {
        $scenario = SpineScenario::make()->build();
        $this->metierFactureALHeure($scenario);
        $scenario->booking->forceFill(['purchased_minutes' => null])->save();

        $mission = $scenario->mission;
        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subHours(9),
        ])->save();

        $this->assertFalse(app(HourlyMissionClock::class)->etat($mission->refresh())['applies']);
    }

    /** L'AUTRE TÉMOIN, et le plus important : du temps acheté ne suffit pas. */
    public function test_du_temps_achete_sur_un_metier_au_forfait_nallume_rien(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 300);

        $mission->booking->resolveTrade()?->forceFill(['hourly_billing' => false])->save();

        $this->assertFalse(app(HourlyMissionClock::class)->etat($mission->refresh())['applies']);
    }

    /** Une mission jamais démarrée n'a pas d'échéance : il n'y a rien à décompter. */
    public function test_sans_demarrage_lhorloge_reste_eteinte(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 0);
        $mission->forceFill(['actual_start_at' => null])->save();

        $this->assertFalse(app(HourlyMissionClock::class)->etat($mission->refresh())['applies']);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function metierFactureALHeure(SpineScenario $scenario): void
    {
        $metier = $scenario->booking->resolveTrade();

        $this->assertNotNull($metier, 'Le scénario doit porter un métier, sinon ce test ne mesure rien.');

        $metier->forceFill(['hourly_billing' => true, 'default_hourly_rate' => 45])->save();
    }

    private function missionAuTemps(int $minutesAchetees, int $prixCents, int $ecouleesMinutes): Mission
    {
        $scenario = SpineScenario::make()->build();

        // Le métier décide du mode de facturation ; sans ce drapeau, le tarif effectif se refuse
        // — et c'est voulu : on ne divise pas un forfait par des heures.
        $this->metierFactureALHeure($scenario);

        $scenario->booking->forceFill([
            'purchased_minutes' => $minutesAchetees,
            'estimated_duration_minutes' => $minutesAchetees,
            'devis_estime' => $prixCents / 100,
            'payment_amount_cents' => $prixCents,
        ])->save();

        $mission = $scenario->mission;
        $mission->forceFill([
            'status' => MissionStatus::STARTED,
            'actual_start_at' => now()->subMinutes($ecouleesMinutes),
            'actual_end_at' => null,
        ])->save();

        return $mission->refresh();
    }
}
