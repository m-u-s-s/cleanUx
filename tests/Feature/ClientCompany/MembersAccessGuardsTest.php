<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\MembersAccess;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES DEUX TROUS RÉELS DE L'ÉCRAN CLIENT — NI PLUS, NI MOINS.
 *
 * Le programme annonçait que `MembersAccess` portait « exactement les mêmes trous » que son
 * équivalent prestataire. Vérification faite, c'est faux : cet écran limitait DÉJÀ ses requêtes à
 * l'organisation active (pas d'IDOR) et exigeait DÉJÀ une permission (pas d'escalade libre).
 *
 * Il lui manquait deux choses, que ces tests figent :
 *
 *   1. HIÉRARCHIE — la garde portait sur le rôle VISÉ, jamais sur la personne visée. Interdire
 *      d'attribuer un rang supérieur au sien n'empêche pas de RÉTROGRADER quelqu'un au-dessus de
 *      soi : le rang visé, lui, est bas.
 *   2. DERNIER PROPRIÉTAIRE — rien n'empêchait de déclasser le seul propriétaire actif, laissant
 *      l'organisation sans personne pour gérer ses accès.
 */
class MembersAccessGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function membre(OrganizationAccount $org, OrganizationRole $role): OrganizationMember
    {
        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function un_manager_ne_peut_pas_retrograder_le_proprietaire(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $proprietaire = $this->membre($org, OrganizationRole::OWNER);
        $manager = $this->membre($org, OrganizationRole::MANAGER);

        Livewire::actingAs($manager->user)
            ->test(MembersAccess::class)
            ->call('changeRole', $proprietaire->id, OrganizationRole::VIEWER->value)
            ->assertForbidden();

        $this->assertSame(
            OrganizationRole::OWNER->value,
            $proprietaire->fresh()->role->value,
            'Le rang visé étant bas, la garde anti-escalade ne se déclenchait pas : le propriétaire tombait.',
        );
    }

    #[Test]
    public function le_dernier_proprietaire_ne_peut_pas_se_retrograder(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $proprietaire = $this->membre($org, OrganizationRole::OWNER);

        Livewire::actingAs($proprietaire->user)
            ->test(MembersAccess::class)
            ->call('changeRole', $proprietaire->id, OrganizationRole::VIEWER->value);

        $this->assertSame(
            OrganizationRole::OWNER->value,
            $proprietaire->fresh()->role->value,
            "Sans propriétaire actif, plus personne ne peut gérer les accès de l'organisation.",
        );
    }

    /**
     * DEUX PAIRS NE PEUVENT PAS SE DÉCLASSER L'UN L'AUTRE.
     *
     * J'attendais d'abord l'inverse — « tant qu'il reste un propriétaire, la rétrogradation
     * passe » — et le test m'a détrompé. `OrganizationRole::canManage()` exige un rang
     * STRICTEMENT supérieur : entre deux propriétaires, personne n'a autorité.
     *
     * Ce n'est pas la garde du dernier propriétaire qui bloque ici (il en resterait un), mais la
     * hiérarchie. La règle est cohérente : elle empêche un coup de force entre associés. Sa
     * conséquence opérationnelle — seul un administrateur de la plateforme peut trancher entre
     * deux propriétaires — est délibérée et vaut d'être figée.
     */
    #[Test]
    public function un_proprietaire_ne_peut_pas_retrograder_son_pair(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();

        $premier = $this->membre($org, OrganizationRole::OWNER);
        $second = $this->membre($org, OrganizationRole::OWNER);

        Livewire::actingAs($premier->user)
            ->test(MembersAccess::class)
            ->call('changeRole', $second->id, OrganizationRole::MANAGER->value)
            ->assertForbidden();

        $this->assertSame(OrganizationRole::OWNER->value, $second->fresh()->role->value);
    }
}
