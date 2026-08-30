<?php

namespace Tests\Feature\Automation;

use App\Listeners\Automation\EnregistrerLAlerteMetier;
use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationReevaluation;
use App\Models\AutomationRun;
use App\Models\Booking;
use App\Models\Mission;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcouteurDAlerteTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_alerte_levee_est_persistee_et_deposee(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $alerte = AlerteMetier::sole();

        $this->assertSame('webhook_backlog', $alerte->cle);
        $this->assertSame(412, $alerte->contexte['count']);
        $this->assertNull($alerte->entite_type);

        $depot = AutomationReevaluation::sole();

        $this->assertSame('alerte.webhook_backlog', $depot->evenement);
        $this->assertSame('alerte', $depot->entite_type);
        $this->assertSame($alerte->id, $depot->entite_id);
    }

    public function test_une_alerte_qui_porte_une_reservation_la_note(): void
    {
        $booking = Booking::factory()->create();

        BusinessAlerts::paymentCaptureFailed($booking);

        $alerte = AlerteMetier::sole();

        $this->assertSame('booking', $alerte->entite_type);
        $this->assertSame($booking->id, $alerte->entite_id);
    }

    /** L'ECOUTEUR ECRIT ET REND LA MAIN. Aucune regle ne tourne dans la requete de
     *  l'utilisateur : `QUEUE_CONNECTION=sync`, tout s'y paierait comptant. */
    public function test_l_ecouteur_ne_declenche_aucun_passage(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $this->assertSame(0, AutomationRun::count());
        $this->assertSame(0, AutomationAction::count());
    }

    /** TEMOIN — l'ecouteur a bien tourne : sans lui, le test ci-dessus serait vert
     *  en mesurant une absence totale d'ecouteur. */
    public function test_temoin_l_ecouteur_a_bien_ecrit(): void
    {
        BusinessAlerts::webhookBacklog(412);

        $this->assertSame(1, AlerteMetier::count());
        $this->assertSame(1, AutomationReevaluation::count());
    }

    public function test_deux_alertes_identiques_font_deux_lignes_et_deux_depots(): void
    {
        BusinessAlerts::webhookBacklog(412);
        BusinessAlerts::webhookBacklog(500);

        // Chaque alerte est un FAIT distinct : deux lignes, deux entites, donc deux depots.
        $this->assertSame(2, AlerteMetier::count());
        $this->assertSame(2, AutomationReevaluation::count());
    }

    /** TOUTE alerte emise doit avoir une decision explicite sur son entite liee — meme
     *  « aucune ». Une cle oubliee ici serait une entite silencieusement perdue. */
    public function test_chaque_alerte_emise_a_une_decision_sur_son_entite(): void
    {
        $cles = ['payment_capture_failed', 'payout_failed', 'webhook_backlog',
            'stuck_mission_holding_funds', 'reconciliation_divergence'];

        $reflexion = new \ReflectionClass(EnregistrerLAlerteMetier::class);
        $table = $reflexion->getConstant('ENTITE_LIEE');

        $manquantes = array_values(array_diff($cles, array_keys($table)));

        $this->assertSame([], $manquantes, 'Alertes sans decision : '.implode(', ', $manquantes));
    }

    /** LE PIEGE MESURE — cette alerte porte `mission_id` ET `booking_id`. C'est la CLE
     *  qui tranche, pas l'ordre de lecture du contexte. */
    public function test_une_mission_bloquee_est_liee_a_la_mission_pas_a_la_reservation(): void
    {
        $mission = Mission::factory()->create();

        BusinessAlerts::stuckMissionHoldingFunds($mission);

        $alerte = AlerteMetier::sole();

        $this->assertSame('mission', $alerte->entite_type);
        $this->assertSame($mission->id, $alerte->entite_id);
        $this->assertNotNull($alerte->contexte['booking_id'] ?? null, 'Le contexte porte bien les deux : la mesure vaut.');
    }
}
