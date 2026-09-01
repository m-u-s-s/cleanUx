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

    /** LA LIGNE DE PARTAGE, ecrite une fois : qui ecrit dans le domaine, et qui pas. */
    private const SANS_DOMAINE = ['journaliser', 'notifier.admins'];

    private const AVEC_DOMAINE = ['mission.ping_client', 'mission.relancer_la_recherche', 'mission.imposer_doffice'];

    public function test_chaque_action_declare_juste_si_elle_ecrit_dans_le_domaine(): void
    {
        $ecarts = [];

        foreach (app(ActionRegistre::class)->toutes() as $cle => $action) {
            $attendu = in_array($cle, self::AVEC_DOMAINE, true);

            if ($action->toucheAuDomaine() !== $attendu) {
                $ecarts[] = $cle.' declare '.var_export($action->toucheAuDomaine(), true);
            }
        }

        $this->assertSame([], $ecarts, implode("\n", $ecarts));
    }

    /** TEMOIN — le registre porte bien les cinq actions. Sans lui, le test ci-dessus
     *  passerait au vert sur un registre vide. */
    public function test_temoin_le_registre_porte_les_cinq_actions(): void
    {
        $cles = array_keys(app(ActionRegistre::class)->toutes());
        $attendues = array_merge(self::SANS_DOMAINE, self::AVEC_DOMAINE);

        sort($cles);
        sort($attendues);

        $this->assertSame($attendues, $cles);
    }
}
