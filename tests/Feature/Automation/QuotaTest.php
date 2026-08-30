<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    private function regle(int $quota): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => 'chaque_passage',
            'quota_par_passage' => $quota,
        ]);
    }

    public function test_le_quota_bride_le_passage_sans_suspendre_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, $passage->entites_vues);
        $this->assertSame('plafond_atteint', $passage->statut);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_trois_plafonds_consecutifs_suspendent_la_regle(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(AutomationRule::ETAT_SUSPENDUE, $regle->fresh()->etat);
    }

    /**
     * TEMOIN — un passage SOUS le plafond remet le compteur a zero. Sans lui, une regle
     * saine finirait suspendue au bout de trois passages charges espaces dans le temps.
     */
    public function test_temoin_un_passage_sous_le_plafond_remet_le_compteur_a_zero(): void
    {
        Booking::factory()->count(5)->create(['status' => 'en_attente']);
        $regle = $this->regle(2);

        app(RuleRunner::class)->executer($regle);
        app(RuleRunner::class)->executer($regle->fresh());
        $this->assertSame(2, $regle->fresh()->plafonds_consecutifs);

        Booking::query()->update(['status' => 'confirme']);   // plus rien a traiter

        app(RuleRunner::class)->executer($regle->fresh());

        $this->assertSame(0, $regle->fresh()->plafonds_consecutifs);
        $this->assertSame(AutomationRule::ETAT_ARMEE, $regle->fresh()->etat);
    }

    public function test_le_plafond_journalier_arrete_la_regle(): void
    {
        Booking::factory()->count(4)->create(['status' => 'en_attente']);

        $regle = $this->regle(10);
        $regle->forceFill(['plafond_journalier' => 2])->save();

        $passage = app(RuleRunner::class)->executer($regle);

        $this->assertSame(2, AutomationAction::count());
        $this->assertSame('plafond_atteint', $passage->statut);
    }
}
