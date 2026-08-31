<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Automation\JournalDeRegle;
use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE JOURNAL D'UNE REGLE — l'ecran qu'on lit AVANT d'armer : ce qu'un passage a vu et pose,
 * et pourquoi il a echoue. Sans lui, l'observation obligatoire ne sert a rien.
 */
class JournalDeRegleTest extends TestCase
{
    use RefreshDatabase;

    private function adminGlobal(): User
    {
        return User::factory()->admin()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'managed_service_zone_id' => null,
            'is_active' => true,
            'permissions' => ['manage-automation'],
        ]);
    }

    private function regle(array $attributs = []): AutomationRule
    {
        return AutomationRule::create(array_merge([
            'nom' => 'Missions sans intervenant',
            'entite' => 'booking',
            'declencheur' => 'cadence',
            'conditions' => [],
            'actions' => [],
        ], $attributs));
    }

    /**
     * LA GARDE DE COMPOSANT — sans `EnforcesAdminAccess`, n'importe quel compte authentifie
     * pourrait monter ce composant : aucune route n'intervient sur /livewire/update.
     */
    public function test_un_non_administrateur_est_bloque_au_niveau_du_composant(): void
    {
        $regle = $this->regle();

        $this->actingAs(User::factory()->client()->create());

        Livewire::test(JournalDeRegle::class, ['regleId' => $regle->id])->assertForbidden();
    }

    /** LE JOURNAL MONTRE LES PASSAGES ET LES LIGNES POSEES — sert aussi de temoin a l'etat vide. */
    public function test_le_journal_montre_les_passages_et_les_lignes_posees(): void
    {
        $regle = $this->regle();

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'observation',
            'demarre_le' => now(),
            'termine_le' => now(),
            'entites_eligibles' => 5,
            'entites_vues' => 5,
            'actions_posees' => 1,
            'statut' => 'ok',
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 42,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now(),
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertSee('Observation')
            ->assertSee('booking #42')
            // LE LIBELLE VIENT DU CATALOGUE, PAS LA CLE BRUTE — meme garde que AutomationCenter
            // pour les declencheurs : rien en dur dans la vue.
            ->assertSee("Écrire au journal d'activité")
            ->assertDontSee('Aucun passage');
    }

    /**
     * LES PARAMETRES D'UNE LIGNE POSEE SONT LISIBLES — AVEC SON TEMOIN. C'est precisement ce que
     * l'admin vient lire avant d'armer : voir qu'une notification "aurait ete envoyee" sans voir
     * SON CONTENU ne permet pas de decider. Le temoin (sans parametre) ne doit rien afficher de
     * bancal — pas de "[]", pas de "null", pas de ligne vide.
     */
    public function test_les_parametres_d_une_ligne_posee_sont_affiches_avec_leur_temoin(): void
    {
        $regle = $this->regle();

        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'observation',
            'demarre_le' => now(),
            'statut' => 'ok',
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 10,
            'action_cle' => 'journaliser',
            'parametres' => ['message' => 'Alerte paiement en échec'],
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now(),
        ]);

        // TEMOIN — sans parametre du tout (colonne NULL en base) : rien de bancal.
        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 11,
            'action_cle' => 'journaliser',
            'mode' => 'observation',
            'resultat' => AutomationAction::RESULTAT_SIMULEE,
            'pose_le' => now(),
        ]);

        $html = Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertSee('message')
            ->assertSee('Alerte paiement en échec')
            ->html();

        // ">[]<"/">null<" et non "[]"/"null" nus : le wire:snapshot de Livewire contient deja
        // des "[]" legitimes (children/scripts/assets/errors), etrangers a cette colonne.
        $this->assertStringNotContainsString('>[]<', $html);
        $this->assertStringNotContainsString('>null<', $html);
    }

    /** UNE REGLE SANS PASSAGE MONTRE UN ETAT VIDE, JAMAIS UN TABLEAU VIDE. */
    public function test_une_regle_sans_passage_affiche_un_etat_vide(): void
    {
        $regle = $this->regle();

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertSee('Aucun passage');
    }

    /**
     * LE FILTRE PAR RESULTAT FILTRE VRAIMENT — AVEC SON TEMOIN : sans filtre, les deux lignes
     * sont visibles ; sans ce temoin, un filtre qui ne filtre rien passerait au vert.
     */
    public function test_le_filtre_par_resultat_filtre_vraiment_avec_son_temoin(): void
    {
        $regle = $this->regle();
        $passage = AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'armee',
            'demarre_le' => now(),
            'statut' => 'ok',
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 1,
            'action_cle' => 'journaliser',
            'mode' => 'armee',
            'resultat' => AutomationAction::RESULTAT_EXECUTEE,
            'pose_le' => now(),
        ]);

        AutomationAction::create([
            'automation_rule_id' => $regle->id,
            'automation_run_id' => $passage->id,
            'entite_type' => 'booking',
            'entite_id' => 2,
            'action_cle' => 'journaliser',
            'mode' => 'armee',
            'resultat' => AutomationAction::RESULTAT_ECHOUEE,
            'pose_le' => now(),
        ]);

        // TEMOIN — sans filtre, les deux lignes apparaissent.
        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertSee('booking #1')
            ->assertSee('booking #2');

        // FILTRE APPLIQUE — seule la ligne echouee reste visible.
        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->set('filtreResultat', AutomationAction::RESULTAT_ECHOUEE)
            ->assertDontSee('booking #1')
            ->assertSee('booking #2');
    }

    /**
     * LE MESSAGE D'UN PASSAGE EN ECHEC EST VISIBLE — c'est lui qui explique une suspension.
     * Un administrateur qui retrouve sa regle suspendue sans pouvoir lire pourquoi est
     * exactement le defaut que cet ecran doit empecher.
     */
    public function test_le_message_d_un_passage_en_echec_est_visible(): void
    {
        $regle = $this->regle();

        AutomationRun::create([
            'automation_rule_id' => $regle->id,
            'mode' => 'armee',
            'demarre_le' => now(),
            'termine_le' => now(),
            'statut' => 'echec',
            'message' => "Règle armée sans journal d'observation.",
        ]);

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->assertSee("Règle armée sans journal d'observation.");
    }

    /**
     * LA GARDE `#[Locked]` — sans elle, le navigateur pourrait retourner `regleId` par `$set`
     * et lire le journal d'une AUTRE regle que celle chargee au montage.
     */
    public function test_la_propriete_regle_id_est_verrouillee(): void
    {
        $regle = $this->regle();
        $autre = $this->regle(['nom' => 'Une autre']);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->adminGlobal())
            ->test(JournalDeRegle::class, ['regleId' => $regle->id])
            ->set('regleId', $autre->id);
    }
}
