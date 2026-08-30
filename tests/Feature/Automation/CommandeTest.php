<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandeTest extends TestCase
{
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
        $this->regle(AutomationRule::ETAT_ARMEE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
    }

    /** TEMOIN — interrupteur ouvert, la meme regle agit. */
    public function test_temoin_l_interrupteur_ouvert_laisse_passer(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_ARMEE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(1, AutomationAction::count());
    }

    public function test_une_regle_en_brouillon_ou_desactivee_ne_tourne_pas(): void
    {
        config()->set('features.automation', true);

        Booking::factory()->create(['status' => 'en_attente']);
        $this->regle(AutomationRule::ETAT_BROUILLON);
        $this->regle(AutomationRule::ETAT_DESACTIVEE);
        $this->regle(AutomationRule::ETAT_SUSPENDUE);

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
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
        $regle = $this->regle(AutomationRule::ETAT_ARMEE, 'jour');
        $regle->forceFill(['dernier_passage_le' => now()->subHour()])->save();

        $this->artisan('automation:executer')->assertExitCode(0);

        $this->assertSame(0, AutomationAction::count());
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
