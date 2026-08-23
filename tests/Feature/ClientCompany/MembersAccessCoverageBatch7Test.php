<?php

namespace Tests\Feature\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\MembersAccess;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Coverage-focused exercise of App\Livewire\ClientCompany\MembersAccess. */
class MembersAccessCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    /** The component orders members with the MySQL-only FIELD() function. */
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
     * Create an OrganizationAccount and a user belonging to it with the given role.
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

    /** Attach an extra member (a brand-new user) to the given organisation. */
    private function makeMember(
        OrganizationAccount $org,
        string $role = OrganizationRole::REQUESTER->value,
        string $status = 'active',
    ): OrganizationMember {
        $user = User::factory()->client()->create([
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
    public function non_company_user_is_forbidden_from_mounting(): void
    {
        $personalClient = User::factory()->client()->create([
            'current_organization_id' => null,
            'organization_account_id' => null,
        ]);

        Livewire::actingAs($personalClient)
            ->test(MembersAccess::class)
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_mount_and_render_with_members(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $this->makeMember($org, OrganizationRole::MANAGER->value);

        $component = Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->assertOk()
            ->assertSet('showInvite', false)
            ->assertSet('showPermissions', false)
            ->assertSet('editingMemberId', null);

        // getMembersProperty + getAvailableRolesProperty exercised through render.
        $this->assertCount(2, $component->instance()->members);
        $this->assertNotEmpty($component->instance()->availableRoles);
    }

    #[Test]
    public function editing_member_computed_is_null_without_id_and_resolves_when_set(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org);

        $component = Livewire::actingAs($user)
            ->test(MembersAccess::class);

        $this->assertNull($component->instance()->editingMember);

        $component->set('editingMemberId', $target->id);
        $this->assertSame($target->id, $component->instance()->editingMember->id);
    }

    #[Test]
    public function invite_adds_an_existing_user_directly_as_active_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $invitee = User::factory()->client()->create([
            'email' => 'newbie@example.com',
        ]);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->set('inviteEmail', 'newbie@example.com')
            ->set('inviteRole', OrganizationRole::REQUESTER->value)
            ->set('showInvite', true)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('showInvite', false)
            ->assertSet('inviteEmail', '')
            ->assertDispatched('member-invited');

        $this->assertDatabaseHas('organization_members', [
            'organization_account_id' => $org->id,
            'user_id' => $invitee->id,
            'role' => OrganizationRole::REQUESTER->value,
            'status' => 'active',
            'invited_by' => $user->id,
        ]);
    }

    #[Test]
    public function invite_for_unknown_email_creates_no_member_but_resets_and_dispatches(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->set('inviteEmail', 'nobody@example.com')
            ->set('inviteMessage', 'Welcome')
            ->set('showInvite', true)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('showInvite', false)
            ->assertSet('inviteEmail', '')
            ->assertSet('inviteMessage', '')
            ->assertDispatched('member-invited');

        // No matching user → no membership row created.
        $this->assertSame(1, OrganizationMember::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function invite_rejects_an_already_existing_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $existing = $this->makeMember($org);
        $existing->user->update(['email' => 'dup@example.com']);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->set('inviteEmail', 'dup@example.com')
            ->set('inviteRole', OrganizationRole::REQUESTER->value)
            ->call('invite')
            ->assertHasErrors('inviteEmail');

        $this->assertSame(2, OrganizationMember::where('organization_account_id', $org->id)->count());
    }

    #[Test]
    public function invite_validates_email_and_role(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->set('inviteEmail', 'not-an-email')
            ->set('inviteRole', OrganizationRole::WORKER->value) // not a client-company role
            ->call('invite')
            ->assertHasErrors([
                'inviteEmail' => 'email',
                'inviteRole' => 'in',
            ]);
    }

    #[Test]
    public function invite_is_forbidden_without_permission(): void
    {
        // REQUESTER lacks members.invite but can still mount (org is a client company).
        [$org, $user] = $this->makeCompanyUser(OrganizationRole::REQUESTER->value);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->set('inviteEmail', 'x@example.com')
            ->call('invite')
            ->assertForbidden();
    }

    #[Test]
    public function change_role_updates_a_lower_ranked_member(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::REQUESTER->value);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->call('changeRole', $target->id, OrganizationRole::MANAGER->value)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'role' => OrganizationRole::MANAGER->value,
        ]);
    }

    #[Test]
    public function change_role_rejects_promotion_to_equal_or_higher_rank(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::REQUESTER->value);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->call('changeRole', $target->id, OrganizationRole::OWNER->value)
            ->assertHasErrors('role');

        $this->assertDatabaseHas('organization_members', [
            'id' => $target->id,
            'role' => OrganizationRole::REQUESTER->value,
        ]);
    }

    #[Test]
    public function change_role_is_forbidden_without_permission(): void
    {
        [$org, $actorUser] = $this->makeCompanyUser(OrganizationRole::REQUESTER->value);
        $target = $this->makeMember($org, OrganizationRole::VIEWER->value);

        Livewire::actingAs($actorUser)
            ->test(MembersAccess::class)
            ->call('changeRole', $target->id, OrganizationRole::REQUESTER->value)
            ->assertForbidden();
    }

    #[Test]
    public function change_role_for_other_org_member_throws_not_found(): void
    {
        [$orgA, $userA] = $this->makeCompanyUser();
        [$orgB] = $this->makeCompanyUser();
        $foreign = $this->makeMember($orgB);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($userA)
            ->test(MembersAccess::class)
            ->call('changeRole', $foreign->id, OrganizationRole::MANAGER->value);
    }

    #[Test]
    public function suspend_reactivate_and_remove_transition_member_status(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::REQUESTER->value);

        $component = Livewire::actingAs($user)->test(MembersAccess::class);

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
            ->test(MembersAccess::class)
            ->call('suspend', $ownMember->id);

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownMember->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function suspend_of_higher_ranked_member_is_a_no_op(): void
    {
        // Manager granted members.suspend via custom permission, but cannot manage an OWNER.
        [$org, $managerUser] = $this->makeCompanyUser(
            OrganizationRole::MANAGER->value,
            ['members.suspend' => true],
        );
        $ownerTarget = $this->makeMember($org, OrganizationRole::OWNER->value);

        Livewire::actingAs($managerUser)
            ->test(MembersAccess::class)
            ->call('suspend', $ownerTarget->id);

        $this->assertDatabaseHas('organization_members', [
            'id' => $ownerTarget->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function suspend_is_forbidden_without_permission(): void
    {
        [$org, $actorUser] = $this->makeCompanyUser(OrganizationRole::REQUESTER->value);
        $target = $this->makeMember($org, OrganizationRole::VIEWER->value);

        Livewire::actingAs($actorUser)
            ->test(MembersAccess::class)
            ->call('suspend', $target->id)
            ->assertForbidden();
    }

    #[Test]
    public function open_permissions_sets_editing_state(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org);

        Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->call('openPermissions', $target->id)
            ->assertSet('editingMemberId', $target->id)
            ->assertSet('showPermissions', true);
    }

    #[Test]
    public function toggle_custom_permission_grants_then_revokes(): void
    {
        [$org, $user] = $this->makeCompanyUser();
        $target = $this->makeMember($org, OrganizationRole::REQUESTER->value);

        $component = Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->call('openPermissions', $target->id);

        $component->call('toggleCustomPermission', 'analytics.view', true);
        $this->assertTrue($target->fresh()->permissions['analytics.view']);

        $component->call('toggleCustomPermission', 'analytics.view', false);
        $this->assertFalse($target->fresh()->permissions['analytics.view']);
    }

    #[Test]
    public function toggle_custom_permission_without_editing_id_is_a_no_op(): void
    {
        [$org, $user] = $this->makeCompanyUser();

        $component = Livewire::actingAs($user)
            ->test(MembersAccess::class)
            ->call('toggleCustomPermission', 'analytics.view', true);

        $component->assertOk();
    }

    #[Test]
    public function toggle_custom_permission_for_other_org_member_is_a_no_op(): void
    {
        [$orgA, $userA] = $this->makeCompanyUser();
        [$orgB] = $this->makeCompanyUser();
        $foreign = $this->makeMember($orgB, OrganizationRole::REQUESTER->value);

        Livewire::actingAs($userA)
            ->test(MembersAccess::class)
            ->set('editingMemberId', $foreign->id)
            ->call('toggleCustomPermission', 'analytics.view', true);

        $this->assertNull($foreign->fresh()->permissions);
    }

    #[Test]
    public function toggle_custom_permission_is_forbidden_without_permission(): void
    {
        [$org, $actorUser] = $this->makeCompanyUser(OrganizationRole::REQUESTER->value);
        $target = $this->makeMember($org);

        Livewire::actingAs($actorUser)
            ->test(MembersAccess::class)
            ->set('editingMemberId', $target->id)
            ->call('toggleCustomPermission', 'analytics.view', true)
            ->assertForbidden();
    }
}
