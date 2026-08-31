<?php

namespace Tests\Feature\Automation;

use App\Events\BusinessAlertRaised;
use App\Listeners\Automation\DeposerLaReevaluation;
use App\Listeners\Automation\EnregistrerLAlerteMetier;
use App\Models\AlerteMetier;
use App\Models\AutomationAction;
use App\Models\AutomationReevaluation;
use App\Models\AutomationRun;
use App\Models\Booking;
use App\Models\Mission;
use App\Providers\EventServiceProvider;
use App\Services\Automation\Registre\EntiteRegistre;
use App\Support\Alerts\BusinessAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class EcouteurDAlerteTest extends TestCase
{
    use ExtraitLesClesEmises;
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

    /**
     * TOUTE alerte emise doit avoir une decision explicite sur son entite liee — meme
     * « aucune ». Une cle oubliee ici serait une entite silencieusement perdue.
     * C3 — la liste des cles vient de la source (clesEmises), plus d'un tableau recopie a la main.
     */
    public function test_chaque_alerte_emise_a_une_decision_sur_son_entite(): void
    {
        $cles = $this->clesEmises();
        $this->assertNotEmpty($cles, 'Aucune cle emise trouvee : la lecture de la source a echoue.');

        $reflexion = new ReflectionClass(EnregistrerLAlerteMetier::class);
        $table = $reflexion->getConstant('ENTITE_LIEE');

        $manquantes = array_values(array_diff($cles, array_keys($table)));

        $this->assertSame([], $manquantes, 'Alertes sans decision : '.implode(', ', $manquantes));
    }

    /**
     * C5 — chaque entite NOMMEE dans ENTITE_LIEE doit etre CONNUE du moteur : ecrire
     * 'reservation' au lieu de 'booking' ne ferait tomber aucun test sans cette garde.
     */
    public function test_chaque_entite_nommee_dans_entite_liee_est_enregistree(): void
    {
        $reflexion = new ReflectionClass(EnregistrerLAlerteMetier::class);
        $table = $reflexion->getConstant('ENTITE_LIEE');

        $entites = array_values(array_unique(array_filter(
            array_map(fn (?array $d): ?string => $d['entite'] ?? null, $table)
        )));

        // ANCRE — sans elle, une table entierement a `null` rendrait ce test vert a vide.
        $this->assertNotEmpty($entites, 'ENTITE_LIEE ne nomme aucune entite (hors null).');

        $connues = app(EntiteRegistre::class)->cles();
        $inconnues = array_values(array_diff($entites, $connues));

        $this->assertSame([], $inconnues, 'Entites inconnues du moteur : '.implode(', ', $inconnues));
    }

    /**
     * C7 — l'invariant des deux ecouteurs : jamais tous les deux sur BusinessAlertRaised, sinon
     * le generique y deposerait un identifiant nul et ne ferait rien, en silence.
     */
    public function test_le_generique_n_ecoute_pas_les_alertes_metier(): void
    {
        $reflexion = new ReflectionClass(EventServiceProvider::class);
        $table = $reflexion->getDefaultProperties()['listen'];

        // ANCRE — sans elle, une table de reflexion vide rendrait ce test vert a vide.
        $this->assertNotEmpty($table, 'La table $listen lue par reflexion est vide.');

        $ecouteurs = $table[BusinessAlertRaised::class] ?? [];

        $this->assertNotContains(
            DeposerLaReevaluation::class,
            $ecouteurs,
            'Le generique ne doit jamais ecouter BusinessAlertRaised : EnregistrerLAlerteMetier le fait deja.'
        );
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
