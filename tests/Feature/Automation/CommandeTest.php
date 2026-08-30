<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\EtatDeRegle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandeTest extends TestCase
{
    use ArmeSesRegles;
    use RefreshDatabase;

    private function regle(string $etat, string $cadence = 'chaque_minute'): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => $cadence,
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => $etat,
        ]);
    }

    public function test_l_interrupteur_ferme_coupe_tout(): void
    {
        config()->set('features.automation', false);

        Booking::factory()->create(['status' => 'en_attente']);
        // Armee par le chemin reel : si l'interrupteur ne l'arretait pas, elle agirait pour
        // de bon — sinon le zero mesure la garde B1, jamais le drapeau.
        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        $regle->forceFill(['dernier_passage_le' => null])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    /** TEMOIN — interrupteur ouvert, la meme regle agit. */
    public function test_temoin_l_interrupteur_ouvert_laisse_passer(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        // Armee par le chemin reel : l'observation a deja pose son `dernier_passage_le`,
        // on le releve pour isoler ce test de la cadence — qui a son propre test plus bas.
        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        $regle->forceFill(['dernier_passage_le' => null])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('mode', 'armee')->count());
    }

    public function test_une_regle_en_brouillon_ou_desactivee_ne_tourne_pas(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        // Brouillon n'a jamais observe : la garde B1 la retiendrait de toute facon.
        $this->regle(AutomationRule::ETAT_BROUILLON);

        // Desactivee et suspendue, elles, ONT observe (chemin reel) : seul le filtre
        // d'etat les retient, la garde B1 les laisserait agir si on l'omettait.
        $desactivee = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(EtatDeRegle::class)->desactiver($desactivee->fresh());
        $desactivee->forceFill(['dernier_passage_le' => null])->save();

        $suspendue = $this->armer($this->regle(AutomationRule::ETAT_ARMEE));
        app(EtatDeRegle::class)->suspendre($suspendue->fresh(), 'test');
        $suspendue->forceFill(['dernier_passage_le' => null])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    public function test_une_regle_en_observation_tourne_et_journalise(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_OBSERVATION);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::where('resultat', 'simulee')->count());
    }

    public function test_une_cadence_non_due_est_sautee(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        // Armee par le chemin reel : sans ca, la garde B1 l'arreterait de toute facon et
        // retirer le filtre de cadence ne ferait tomber personne.
        $regle = $this->armer($this->regle(AutomationRule::ETAT_ARMEE, 'jour'));
        $regle->forceFill(['dernier_passage_le' => now()->subHour()])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::where('mode', 'armee')->count());
    }

    /** L'INTERRUPTEUR EST FERME A LA LIVRAISON. Un moteur qui s'allume seul au deploiement
     *  n'est pas un interrupteur. */
    public function test_le_drapeau_est_livre_ferme(): void
    {
        $livre = require config_path('features.php');

        $this->assertArrayHasKey(
            'automation',
            $livre,
            'Sans la cle, isEnabled() rend false sans que personne comprenne pourquoi.'
        );
        $this->assertFalse($livre['automation'], 'Le moteur serait arme des le premier deploiement.');
    }
}
