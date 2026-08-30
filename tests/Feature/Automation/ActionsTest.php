<?php

namespace Tests\Feature\Automation;

use App\Models\Booking;
use App\Models\User;
use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Registre\ActionRegistre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_journaliser_ecrit_dans_le_journal_d_activite(): void
    {
        $reservation = Booking::factory()->create();

        $resultat = (new Journaliser)->executer($reservation, ['message' => 'reperee']);

        $this->assertTrue($resultat->reussie);
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.note']);
    }

    public function test_notifier_les_admins_les_previent_tous(): void
    {
        Notification::fake();

        User::factory()->admin()->count(2)->create(['is_active' => true]);
        $reservation = Booking::factory()->create();

        $resultat = (new NotifierLesAdmins)->executer($reservation, ['message' => 'a traiter']);

        $this->assertTrue($resultat->reussie);
        Notification::assertCount(2);
    }

    /** Sans destinataire, l'action ECHOUE au lieu de faire semblant d'avoir reussi. */
    public function test_notifier_sans_aucun_admin_actif_echoue(): void
    {
        Notification::fake();

        $reservation = Booking::factory()->create();

        $resultat = (new NotifierLesAdmins)->executer($reservation, ['message' => 'a traiter']);

        $this->assertFalse($resultat->reussie);
        Notification::assertNothingSent();
    }

    public function test_aucune_action_de_la_phase_1_n_ecrit_dans_le_domaine(): void
    {
        foreach (app(ActionRegistre::class)->toutes() as $action) {
            $this->assertFalse(
                $action->toucheAuDomaine(),
                "L'action {$action->cle()} ecrit dans le domaine : interdit en phase 1."
            );
        }
    }

    /** TEMOIN — le registre porte bien les deux actions. Sans lui, le test ci-dessus
     *  passerait au vert sur un registre vide. */
    public function test_temoin_le_registre_porte_les_deux_actions(): void
    {
        $cles = array_keys(app(ActionRegistre::class)->toutes());

        sort($cles);

        $this->assertSame(['journaliser', 'notifier.admins'], $cles);
    }
}
