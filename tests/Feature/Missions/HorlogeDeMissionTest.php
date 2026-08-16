<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Services\Missions\HourlyMissionClock;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * L'HORLOGE DE DÉPASSEMENT — de l'argent, donc prouvé au centime.
 *
 * Ce que ces tests fixent, et qu'aucune relecture ne rattraperait :
 *
 *   — la franchise est une FRANCHISE, on la déduit ; elle n'est pas un simple seuil au-delà
 *     duquel tout redevient facturable ;
 *   — le ×1,30 s'EMPILE sur les majorations déjà appliquées, il ne les remplace pas ;
 *   — le plafond est appliqué APRÈS l'arrondi, sans quoi l'arrondi le franchit ;
 *   — une mission qui n'est pas vendue au temps n'a pas d'horloge du tout.
 */
class HorlogeDeMissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LE CAS DE RÉFÉRENCE, celui de la spécification.
     *
     * 3 h achetées à 58,50 €/h effectif (45 € majorés ×1,30 par le mode immédiat). Le prestataire
     * fait 4 h. Une heure de dépassement, franchise de 15 min déduite → 45 min facturables,
     * arrondies au quart d'heure : 45 min. 58,50 × 0,75 × 1,30 = 57,04 €.
     */
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

    /**
     * LA FRANCHISE PROTÈGE LE PRESTATAIRE QUI RANGE SES AFFAIRES.
     *
     * Sans elle, cinq minutes de rangement coûteraient une pénalité au client — et le prestataire
     * prendrait l'habitude de clôturer avant d'avoir fini pour l'éviter.
     */
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

    /**
     * LE PLAFOND BORNE L'ABUS — et il est appliqué APRÈS l'arrondi.
     *
     * Trois heures achetées, compteur laissé tourner sept heures. Sans plafond, le client
     * découvrirait quatre heures de pénalité qu'il n'a jamais acceptées.
     */
    public function test_le_plafond_arrete_le_compteur(): void
    {
        $mission = $this->missionAuTemps(minutesAchetees: 180, prixCents: 17550, ecouleesMinutes: 600);

        $etat = app(HourlyMissionClock::class)->etat($mission);

        $this->assertSame(180, $etat['billable_overtime_minutes'], 'Jamais plus que la durée achetée.');
        $this->assertTrue($etat['capped']);
        // 58,50 € × 3 h × 1,30
        $this->assertSame(22815, $etat['overtime_amount_cents']);
    }

    /**
     * LE PIÈGE D'ORDRE : plafonner avant d'arrondir laisserait l'arrondi repasser au-dessus.
     *
     * Dépassement de 3 h 10 sur 3 h achetées : 190 − 15 = 175 min, arrondies à 180. Le plafond vaut
     * 180 : la valeur doit rester 180 et non 195, ce qui serait le résultat d'un arrondi appliqué
     * après le plafonnement.
     */
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

    /**
     * TÉMOIN — sans cette assertion, tous les tests ci-dessus passeraient au vert en mesurant une
     * horloge qui ne s'allume jamais.
     */
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

    /**
     * L'AUTRE TÉMOIN, et le plus important : du temps acheté ne suffit pas.
     *
     * Une réservation peut porter `purchased_minutes` alors que l'administrateur a depuis décoché
     * « paiement à l'heure » sur le métier. Le compteur défilerait, annoncerait un dépassement, et
     * réclamerait zéro euro — puisque le tarif effectif se refuse sur un forfait.
     */
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
            'duree_estimee' => $minutesAchetees,
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
