<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationActionSetting;
use App\Models\User;
use App\Services\Automation\ReglagesDActions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'absence de reglage vaut « a valider ». Jamais l'inverse — voir estAutonome() et tous(). */
class ReglagesDActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_action_sans_ligne_n_est_pas_autonome(): void
    {
        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('journaliser'));
    }

    /** TEMOIN — sans lui, le defaut du test precedent pourrait cacher une bascule qui ne fait rien. */
    public function test_une_action_basculee_vers_autonome_l_est(): void
    {
        $admin = User::factory()->create();

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $this->assertTrue(app(ReglagesDActions::class)->estAutonome('journaliser'));
    }

    public function test_la_bascule_ecrit_modifie_par_et_modifie_le(): void
    {
        $admin = User::factory()->create();

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $reglage = AutomationActionSetting::where('action_cle', 'journaliser')->firstOrFail();

        $this->assertSame($admin->id, $reglage->modifie_par);
        $this->assertNotNull($reglage->modifie_le);
    }

    public function test_la_bascule_est_journalisee(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);

        app(ReglagesDActions::class)->basculer('journaliser', true, $admin);

        $reglage = AutomationActionSetting::where('action_cle', 'journaliser')->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'automation.reglage_autonome',
            'user_id' => $admin->id,
            'target_type' => AutomationActionSetting::class,
            'target_id' => $reglage->id,
        ]);
    }

    public function test_rebasculer_vers_a_valider_fonctionne_et_se_journalise(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        $service = app(ReglagesDActions::class);

        $service->basculer('journaliser', true, $admin);
        $service->basculer('journaliser', false, $admin);

        $this->assertFalse($service->estAutonome('journaliser'));
        $this->assertDatabaseHas('activity_logs', ['action' => 'automation.reglage_a_valider']);
    }

    /** TEMOIN — une ligne normale s'insere sans probleme : le prochain test mesure un vrai refus. */
    public function test_temoin_une_ligne_par_cle_s_insere_normalement(): void
    {
        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => true]);

        $this->assertDatabaseCount('automation_action_settings', 1);
    }

    public function test_une_seconde_ligne_pour_la_meme_cle_est_refusee(): void
    {
        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => true]);

        $this->expectException(QueryException::class);

        AutomationActionSetting::create(['action_cle' => 'journaliser', 'autonome' => false]);
    }

    /** LE GARDE-FOU — un reglage laisse par une action retiree du code ne doit pas ressusciter. */
    public function test_tous_ne_rend_que_les_cles_enregistrees_au_registre(): void
    {
        AutomationActionSetting::create(['action_cle' => 'notifier.admins', 'autonome' => true]);
        AutomationActionSetting::create(['action_cle' => 'action_retiree_du_code', 'autonome' => true]);

        $tous = app(ReglagesDActions::class)->tous();

        $this->assertTrue($tous['notifier.admins']);
        $this->assertFalse($tous['journaliser']);

        // TOUTE ACTION AJOUTEE AU CODE ARRIVE « A VALIDER » — nommees, pas deduites du registre :
        // comparer `tous()` a son propre registre reviendrait a comparer l'implementation a elle-meme.
        foreach (['mission.ping_client', 'mission.relancer_la_recherche'] as $neuve) {
            $this->assertArrayHasKey($neuve, $tous, $neuve);
            $this->assertFalse($tous[$neuve], $neuve);
        }

        $this->assertArrayNotHasKey('action_retiree_du_code', $tous);
    }

    /** Meme garde-fou, applique au point de lecture unitaire : la cle inconnue reste « a valider ». */
    public function test_est_autonome_ignore_un_reglage_orphelin_d_une_action_retiree(): void
    {
        AutomationActionSetting::create(['action_cle' => 'action_retiree_du_code', 'autonome' => true]);

        $this->assertFalse(app(ReglagesDActions::class)->estAutonome('action_retiree_du_code'));
    }

    /** LE DEFAUT DE LA COLONNE — l'invariant central : une ligne sans valeur explicite vaut « a valider ». */
    public function test_une_ligne_creee_sans_preciser_autonome_est_a_valider(): void
    {
        $reglage = AutomationActionSetting::create(['action_cle' => 'journaliser']);

        $this->assertFalse($reglage->fresh()->autonome);
    }

    /** TEMOIN — une ligne qui precise autonome=true se relit bien autonome, le defaut ne l'ecrase pas. */
    public function test_temoin_une_ligne_creee_avec_autonome_vrai_se_relit_autonome(): void
    {
        $reglage = AutomationActionSetting::create(['action_cle' => 'notifier.admins', 'autonome' => true]);

        $this->assertTrue($reglage->fresh()->autonome);
    }
}
