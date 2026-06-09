<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage-focused exercise of App\Livewire\ProviderCompany\TeamManagement.
 *
 * Drives the mount guard, the computed properties, the invitation flow
 * (existing user / unknown email / duplicate / validation / hierarchy guard),
 * role changes (success / rank guard / forbidden / cross-org), status
 * transitions (suspend / reactivate / remove and the self no-op / permission
 * guard) and the custom-permission toggles.
 */
class TeamManagementCoverageBatch8Test extends TestCase
{
    use RefreshDatabase;

    /**
     * The component orders members with the MySQL-only FIELD() function. The
     * test database is SQLite, which lacks it, so register a compatible UDF on
     * the active connection before any render happens. This touches only the
     * test harness — never the component, config or migrations.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FIELD', function ($value, ...$list) {
                $index = array_search($value, $list, false);

                return $index === false ? 0 : $index + 1;
            });
        }
    }

    /**
     * Create an OrganizationAccount plus a user belonging to it with the role.
     *
     * @return array{0: OrganizationAccount, 1: User, 2: OrganizationMember}
     */
    private function makeCompanyUser(
        string $role = OrganizationRole::OWNER->value,
        ?array $permissions = null,
    ): array {
        $org = OrganizationAccount::factory()->create();

        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        $member = OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => $permissions,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $user, $member];
    }

    /**
     * Attach an extra member (a brand-new user) to the given organisation.
     */
    private function makeMember(
        OrganizationAccount $org,
        string $role = OrganizationRole::WORKER->value,
        string $status = 'active',
    ): OrganizationMember {
        $user = User::factory()->employe()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
            'permissions' => null,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function user_without_invite_permission_is_forbidden_from_mounting(): void
    {
        $personal = User::factory()->employe()->create([
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        Livewire::actingAs($personal)
            ->test(TeamManagement::class)
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_mount_and_render_with_members(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $this->makeMember($org, OrganizationRole::DISPATCHER->value);

        $component = Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->assertOk()
            ->assertSet('showInvite', false)
            ->assertSet('showPermissions', false)
            ->assertSet('editingMemberId', null);

        $this->assertCount(2, $component->instance()->members);
        $this->assertNotEmpty($component->instance()->availableRoles);
    }

    #[Test]
    public function members_computed_honours_search_role_and_status_filters(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $worker = $this->makeMember($org, OrganizationRole::WORKER->value);
        $worker->user->update(['name' => 'Zlatan Cleaner', 'email' => 'zlatan@example.com']);
        $this->makeMember($org, OrganizationRole::DISPATCHER->value, 'suspended');

        $component = Livewire::actingAs($user)->test(TeamManagement::class);

        $component->set('filterRole', OrganizationRole::WORKER->value);
        $this->assertCount(1, $component->instance()->members);

        $component->set('filterRole', '')->set('searchQuery', 'Zlatan');
        $this->assertCount(1, $component->instance()->members);

        $component->set('searchQuery', '')->set('filterStatus', 'suspended');
        $this->assertCount(1, $component->instance()->members);
    }

    #[Test]
    public function editing_member_computed_is_null_without_id_and_resolves_when_set(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org);

        $component = Livewire::actingAs($user)->test(TeamManagement::class);

        $this->assertNull($component->instance()->editingMember);

        $component->set('editingMemberId', $target->id);
        $this->assertSame($target->id, $component->instance()->editingMember->id);
    }

    #[Test]
    public function invite_adds_an_existing_user_directly_as_active_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $invitee = User::factory()->employe()->create([
            'email' => 'newbie@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'newbie@example.com')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->set('inviteNote', 'Welcome aboard')
            ->set('showInvite', true)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('showInvite', false)
            ->assertSet('inviteEmail', '')
            ->assertSet('inviteNote', '');

        $this->assertDatabaseHas('organization_members', [
            'organization_account_id' => $org->id,
            'user_id' => $invitee->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_by' => $user->id,
        ]);
    }

    #[Test]
    public function invite_for_unknown_email_creates_no_member_but_resets(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'nobody@example.com')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->set('showInvite', true)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('showInvite', false)
            ->assertSet('inviteEmail', '');

        $this->assertSame(1, OrganizationMember::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function invite_rejects_an_already_existing_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $existing = $this->makeMember($org);
        $existing->user->update(['email' => 'dup@example.com']);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'dup@example.com')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->call('invite')
            ->assertHasErrors('inviteEmail');

        $this->assertSame(2, OrganizationMember::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function invite_validates_the_email_format(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'not-an-email')
            ->set('inviteRole', OrganizationRole::WORKER->value)
            ->call('invite')
            ->assertHasErrors(['inviteEmail' => 'email']);
    }

    #[Test]
    public function invite_blocks_a_role_at_or_above_the_actor_rank(): void
    {
        // Operations manager (rank 80) holds members.invite but may not invite an OWNER (rank 100).
        [$org, $user] = $this->makeCompanyUser(OrganizationRole::OPERATIONS_MANAGER->value);

        User::factory()->employe()->create(['email' => 'wannabe@example.com']);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->set('inviteEmail', 'wannabe@example.com')
            ->set('inviteRole', OrganizationRole::OWNER->value)
            ->call('invite')
            ->assertHasErrors('inviteRole');

        $this->assertSame(1, OrganizationMember::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function change_role_updates_a_lower_ranked_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('changeRole', $target->id, OrganizationRole::DISPATCHER->value)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'role' => OrganizationRole::DISPATCHER->value,
        ]);
    }

    #[Test]
    public function change_role_to_equal_or_higher_rank_is_a_no_op(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        // Owner (rank 100) promoting to OWNER (rank 100) hits the >= guard.
        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('changeRole', $target->id, OrganizationRole::OWNER->value);

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'role' => OrganizationRole::WORKER->value,
        ]);
    }

    #[Test]
    public function change_role_is_forbidden_without_edit_role_permission(): void
    {
        // Operations manager can mount (members.invite) but lacks members.edit_role.
        [$org, $user] = $this->makeCompanyUser(OrganizationRole::OPERATIONS_MANAGER->value);
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('changeRole', $target->id, OrganizationRole::DISPATCHER->value)
            ->assertForbidden();
    }

    #[Test]
    public function change_role_for_a_foreign_org_member_throws_not_found(): void
    {
        [$orgA, $userA] = $this->makeCompanyUser();
        [$orgB] = $this->makeCompanyUser();
        $foreign = $this->makeMember($orgB);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)
            ->test(TeamManagement::class)
            ->call('changeRole', $foreign->id, OrganizationRole::DISPATCHER->value);
    }

    #[Test]
    public function suspend_reactivate_and_remove_transition_member_status(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        $component = Livewire::actingAs($user)->test(TeamManagement::class);

        $component->call('suspend', $target->id);
        $this->assertDatabaseHas('organization_members', ['id' => $target->id, 'status' => 'suspended']);

        $component->call('reactivate', $target->id);
        $this->assertDatabaseHas('organization_members', ['id' => $target->id, 'status' => 'active']);

        $component->call('remove', $target->id);
        $this->assertDatabaseHas('organization_members', ['id' => $target->id, 'status' => 'left']);
    }

    #[Test]
    public function suspend_self_is_a_no_op(): void
    {
        [$org, $user, $ownMember] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('suspend', $ownMember->id);

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownMember->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function suspend_is_forbidden_without_permission(): void
    {
        [$org, $user] = $this->makeCompanyUser(OrganizationRole::OPERATIONS_MANAGER->value);
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('suspend', $target->id)
            ->assertForbidden();
    }

    #[Test]
    public function open_permissions_sets_editing_state(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org);

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('openPermissions', $target->id)
            ->assertSet('editingMemberId', $target->id)
            ->assertSet('showPermissions', true);
    }

    #[Test]
    public function toggle_permission_grants_then_revokes(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::WORKER->value);

        $component = Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('openPermissions', $target->id);

        $component->call('togglePermission', 'analytics.view', true);
        $this->assertTrue($target->fresh()->permissions['analytics.view']);

        $component->call('togglePermission', 'analytics.view', false);
        $this->assertFalse($target->fresh()->permissions['analytics.view']);
    }

    #[Test]
    public function toggle_permission_without_editing_id_is_a_no_op(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(TeamManagement::class)
            ->call('togglePermission', 'analytics.view', true)
            ->assertOk();
    }
}
