<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Livewire\ProviderCompany\TaskBoard;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LOT 1 — LES DEUX ÉCRANS QUE LE WORKER PEUT LÉGITIMEMENT OUVRIR.
 *
 * Le tableau de bord et le tableau des tâches ne se refusent pas : un exécutant y a de vraies
 * choses à voir. Leur garde n'est donc pas à l'entrée mais DANS LA REQUÊTE — ce qui les rend
 * beaucoup plus faciles à laisser trop ouverts qu'un écran qu'on ferme d'un `abort_unless`.
 *
 * Trois fuites y vivaient : le trombinoscope complet de la société sur le tableau de bord, toutes
 * les tâches de la maison sur le tableau des tâches, et — la plus sérieuse — une propriété Livewire
 * PUBLIQUE portant la décision d'autorisation, que le navigateur pouvait retourner lui-même.
 */
class RbacEcransSocieteTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationAccount $org;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function membre(OrganizationRole $role): User
    {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $this->org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $user->providerProfile()->create([
            'organization_account_id' => $this->org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        OrganizationMember::create([
            'organization_account_id' => $this->org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    // ──────────────────────────────────────────────────────
    // Tableau de bord — le trombinoscope et l'effectif
    // ──────────────────────────────────────────────────────

    public function test_le_worker_ne_voit_ni_le_trombinoscope_ni_l_effectif(): void
    {
        /*
         * `getTeamStatusProperty()` n'avait aucune garde : noms, photos et SOUS-RÔLES de toute la
         * société s'affichaient sur le tableau de bord d'un nettoyeur. Le sous-rôle dit qui commande
         * qui — ce n'est pas une donnée de panneau latéral.
         */
        $worker = $this->membre(OrganizationRole::WORKER);
        $this->membre(OrganizationRole::OWNER);

        $composant = Livewire::actingAs($worker)->test(ProviderDashboard::class);

        $this->assertCount(0, $composant->instance()->teamStatus);
        $this->assertNull(
            $composant->instance()->kpis['members_active'],
            'L’effectif de la société ne doit pas être compté pour un exécutant.'
        );
    }

    public function test_l_owner_voit_le_trombinoscope_et_l_effectif(): void
    {
        $owner = $this->membre(OrganizationRole::OWNER);
        $this->membre(OrganizationRole::WORKER);

        $composant = Livewire::actingAs($owner)->test(ProviderDashboard::class);

        $this->assertCount(2, $composant->instance()->teamStatus);
        $this->assertSame(2, $composant->instance()->kpis['members_active']);
    }

    public function test_le_responsable_qualite_suit_les_missions_sans_voir_l_equipe(): void
    {
        /*
         * `missions.view_all` et `team.view` sont deux questions distinctes : combien de missions
         * tournent, et qui travaille ici. Le responsable qualité a la première, pas la seconde.
         * Les confondre aurait rouvert le trombinoscope par la porte des missions.
         */
        $qualite = $this->membre(OrganizationRole::QUALITY_MANAGER);

        $composant = Livewire::actingAs($qualite)->test(ProviderDashboard::class);

        $this->assertTrue($composant->instance()->peutToutVoir);
        $this->assertFalse($composant->instance()->peutVoirLEquipe);
        $this->assertCount(0, $composant->instance()->teamStatus);
    }

    public function test_la_decision_d_autorisation_ne_se_change_pas_depuis_le_navigateur(): void
    {
        /*
         * LE TROU LE PLUS SÉRIEUX DE CET ÉCRAN. `peutToutVoir` est une propriété PUBLIQUE Livewire :
         * elle fait l'aller-retour avec le navigateur, et le client peut demander sa mise à jour.
         * Un `$set('peutToutVoir', true)` depuis la console retournait donc la garde qui décide si
         * l'on voit les missions de toute la société — la vérification faite au montage ne valant
         * plus rien à la requête suivante.
         *
         * `#[Locked]` fait refuser l'écriture CÔTÉ SERVEUR. Le test presse le bouton plutôt que de
         * lire l'attribut : c'est le refus qu'on veut, pas sa déclaration.
         */
        $worker = $this->membre(OrganizationRole::WORKER);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($worker)
            ->test(ProviderDashboard::class)
            ->set('peutToutVoir', true);
    }

    public function test_les_taches_comptees_sur_le_tableau_de_bord_sont_celles_qu_on_peut_ouvrir(): void
    {
        // Le compteur annonçait toutes les tâches de la société, y compris à qui n'en voit aucune
        // une fois le tableau ouvert : un travail introuvable, annoncé en gros chiffres.
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        Task::create([
            'organization_account_id' => $this->org->id,
            'created_by' => $owner->id,
            'title' => 'Relancer le client Dupont',
            'status' => Task::STATUS_TODO,
            'priority' => Task::PRIORITY_MEDIUM,
        ]);

        $composant = Livewire::actingAs($worker)->test(ProviderDashboard::class);

        $this->assertSame(0, $composant->instance()->kpis['pending_tasks']);
    }

    // ──────────────────────────────────────────────────────
    // Tableau des tâches — mes tâches contre celles de la maison
    // ──────────────────────────────────────────────────────

    private function tache(User $auteur, string $titre): Task
    {
        return Task::create([
            'organization_account_id' => $this->org->id,
            'created_by' => $auteur->id,
            'title' => $titre,
            'status' => Task::STATUS_TODO,
            'priority' => Task::PRIORITY_MEDIUM,
        ]);
    }

    public function test_le_worker_ne_voit_que_ses_taches(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $lasienne = $this->tache($worker, 'Racheter des sacs');
        $celleDuPatron = $this->tache($owner, 'Négocier le loyer du dépôt');
        $celleQuOnLuiConfie = $this->tache($owner, 'Passer au 3e étage');
        $celleQuOnLuiConfie->assignees()->attach($worker->id, ['assigned_at' => now()]);

        $titres = Livewire::actingAs($worker)->test(TaskBoard::class)
            ->instance()->todoTasks->pluck('title')->all();

        $this->assertContains($lasienne->title, $titres);
        $this->assertContains($celleQuOnLuiConfie->title, $titres, 'Une tâche qu’on lui confie le regarde.');
        $this->assertNotContains($celleDuPatron->title, $titres);
    }

    public function test_le_dispatcher_voit_tout_le_tableau(): void
    {
        // `tasks.assign` est la clé qui décide : distribuer le travail suppose de voir le tableau.
        $dispatcher = $this->membre(OrganizationRole::DISPATCHER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->tache($owner, 'Négocier le loyer du dépôt');

        $titres = Livewire::actingAs($dispatcher)->test(TaskBoard::class)
            ->instance()->todoTasks->pluck('title')->all();

        $this->assertContains('Négocier le loyer du dépôt', $titres);
    }

    public function test_le_worker_ne_deplace_pas_la_tache_qu_il_ne_voit_pas(): void
    {
        /*
         * L'identifiant vient du navigateur. Les deux écritures chargeaient la tâche sur la seule
         * organisation, puis la gardaient par `tasks.create` — accordée jusqu'au nettoyeur : chacun
         * pouvait marquer « terminée » la tâche d'un collègue en devinant un identifiant.
         */
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $celleDuPatron = $this->tache($owner, 'Négocier le loyer du dépôt');

        Livewire::actingAs($worker)->test(TaskBoard::class)
            ->call('updateStatus', $celleDuPatron->id, Task::STATUS_DONE);

        $this->assertSame(Task::STATUS_TODO, $celleDuPatron->fresh()->status);
    }

    public function test_le_worker_ne_supprime_pas_la_tache_qu_il_ne_voit_pas(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $celleDuPatron = $this->tache($owner, 'Négocier le loyer du dépôt');

        Livewire::actingAs($worker)->test(TaskBoard::class)
            ->call('deleteTask', $celleDuPatron->id);

        $this->assertNotNull($celleDuPatron->fresh(), 'La tâche d’un autre ne doit pas disparaître.');
    }

    public function test_le_worker_n_a_pas_le_trombinoscope_du_selecteur_d_assignation(): void
    {
        // Le tableau des tâches est le seul écran société qu'un exécutant ouvre : la liste des
        // membres y serait la dernière publication de l'effectif.
        $worker = $this->membre(OrganizationRole::WORKER);
        $this->membre(OrganizationRole::OWNER);

        $this->assertCount(
            0,
            Livewire::actingAs($worker)->test(TaskBoard::class)->instance()->members
        );
    }

    // ──────────────────────────────────────────────────────
    // API — la même règle des deux côtés
    // ──────────────────────────────────────────────────────

    public function test_l_api_des_taches_applique_la_meme_regle_que_l_ecran(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $this->tache($worker, 'Racheter des sacs');
        $this->tache($owner, 'Négocier le loyer du dépôt');

        $reponse = $this->actingAs($worker, 'sanctum')
            ->getJson('/api/provider/company/tasks')
            ->assertOk();

        $titres = array_column($reponse->json('data'), 'title');

        $this->assertContains('Racheter des sacs', $titres);
        $this->assertNotContains('Négocier le loyer du dépôt', $titres);
    }

    public function test_l_api_refuse_de_deplacer_une_tache_invisible(): void
    {
        $worker = $this->membre(OrganizationRole::WORKER);
        $owner = $this->membre(OrganizationRole::OWNER);

        $celleDuPatron = $this->tache($owner, 'Négocier le loyer du dépôt');

        $this->actingAs($worker, 'sanctum')
            ->patchJson("/api/provider/company/tasks/{$celleDuPatron->id}", ['status' => 'done'])
            ->assertNotFound();

        $this->assertSame(Task::STATUS_TODO, $celleDuPatron->fresh()->status);
    }
}
