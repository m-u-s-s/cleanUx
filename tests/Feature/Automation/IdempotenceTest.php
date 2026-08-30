<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\Booking;
use App\Services\Automation\RuleRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdempotenceTest extends TestCase
{
    use RefreshDatabase;

    private function regle(string $politique): AutomationRule
    {
        return AutomationRule::create([
            'nom' => 'Les réservations en attente',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'cadence' => 'quart_heure',
            'conditions' => ['field' => 'statut', 'op' => 'eq', 'value' => 'en_attente'],
            'actions' => [['cle' => 'journaliser', 'parametres' => ['message' => 'vue']]],
            'etat' => AutomationRule::ETAT_ARMEE,
            'politique_reprise' => $politique,
        ]);
    }

    public function test_une_fois_n_agit_qu_une_seule_fois_par_entite(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois');

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle);

        $this->assertSame(0, $second->entites_vues);
        $this->assertSame(1, AutomationAction::count());
    }

    public function test_chaque_passage_agit_a_chaque_fois(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('chaque_passage');

        app(RuleRunner::class)->executer($regle);
        $second = app(RuleRunner::class)->executer($regle);

        $this->assertSame(1, $second->entites_vues);
        $this->assertSame(2, AutomationAction::count());
    }

    public function test_une_fois_par_jour_reagit_le_lendemain(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois_par_jour');

        app(RuleRunner::class)->executer($regle);

        $memeJour = app(RuleRunner::class)->executer($regle);
        $this->assertSame(0, $memeJour->entites_vues);

        $this->travel(25)->hours();

        $lendemain = app(RuleRunner::class)->executer($regle);
        $this->assertSame(1, $lendemain->entites_vues);
    }

    /**
     * TEMOIN — l'exclusion vise l'ENTITE, pas la regle entiere. Une reservation neuve
     * est vue au passage suivant, meme en politique `une_fois`.
     */
    public function test_temoin_une_entite_neuve_est_vue_au_passage_suivant(): void
    {
        Booking::factory()->create(['status' => 'en_attente']);
        $regle = $this->regle('une_fois');

        app(RuleRunner::class)->executer($regle);

        Booking::factory()->create(['status' => 'en_attente']);

        $this->assertSame(1, app(RuleRunner::class)->executer($regle)->entites_vues);
    }
}
