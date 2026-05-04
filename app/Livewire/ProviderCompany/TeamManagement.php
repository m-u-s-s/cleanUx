<?php

namespace App\Livewire\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TeamManagement extends Component
{
    use WithPagination;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool   $showInvite      = false;
    public bool   $showPermissions = false;
    public ?int   $editingMemberId = null;
    public string $searchQuery     = '';
    public string $filterRole      = '';
    public string $filterStatus    = 'active';
    public string $activeTab       = 'members'; // members | invitations | performance

    // Formulaire invitation
    public string $inviteEmail = '';
    public string $inviteRole  = OrganizationRole::WORKER->value;
    public string $inviteNote  = '';

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        $user = Auth::user();
        abort_unless(
            app(PermissionService::class)->can($user, 'members.invite', $user->currentOrganization),
            403
        );
    }

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getMembersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterRole, fn ($q) => $q->where('role', $this->filterRole))
            ->when($this->searchQuery, fn ($q) =>
                $q->whereHas('user', fn ($u) =>
                    $u->where('name', 'like', "%{$this->searchQuery}%")
                      ->orWhere('email', 'like', "%{$this->searchQuery}%")
                )
            )
            ->with(['user:id,name,email,profile_photo_path', 'invitedBy:id,name'])
            ->orderByRaw("FIELD(role, 'owner','operations_manager','dispatcher','team_lead','quality_manager','finance','worker','viewer')")
            ->get();
    }

    public function getAvailableRolesProperty(): array
    {
        return OrganizationRole::forProviderCompany();
    }

    public function getEditingMemberProperty(): ?OrganizationMember
    {
        return $this->editingMemberId
            ? OrganizationMember::with('user:id,name,email,profile_photo_path')->find($this->editingMemberId)
            : null;
    }

    // ──────────────────────────────────────────────────────
    // Invitation
    // ──────────────────────────────────────────────────────
    public function invite(): void
    {
        $actor = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($actor, 'members.invite', $actor->currentOrganization),
            403
        );

        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteRole'  => ['required'],
        ]);

        $orgId       = $actor->current_organization_id;
        $targetUser  = User::where('email', $this->inviteEmail)->first();

        // Vérifier hiérarchie
        $actorMember   = $actor->membershipIn();
        $newRoleEnum   = OrganizationRole::from($this->inviteRole);

        if ($actorMember && $newRoleEnum->rank() >= $actorMember->role->rank() && ! $actor->isPlatformAdmin()) {
            $this->addError('inviteRole', 'Vous ne pouvez pas inviter avec un rôle supérieur ou égal au vôtre.');
            return;
        }

        if ($targetUser) {
            $alreadyIn = OrganizationMember::where('organization_account_id', $orgId)
                ->where('user_id', $targetUser->id)
                ->whereIn('status', ['active', 'invited'])
                ->exists();

            if ($alreadyIn) {
                $this->addError('inviteEmail', 'Cet utilisateur est déjà dans l\'organisation.');
                return;
            }

            OrganizationMember::create([
                'organization_account_id' => $orgId,
                'user_id'                 => $targetUser->id,
                'role'                    => $this->inviteRole,
                'status'                  => 'active',
                'invited_by'              => $actor->id,
                'invited_at'              => now(),
                'joined_at'               => now(),
            ]);
        } else {
            // TODO: Envoyer un email d'invitation et créer un token
        }

        $this->reset(['inviteEmail', 'inviteRole', 'inviteNote', 'showInvite']);
    }

    // ──────────────────────────────────────────────────────
    // Gestion des membres
    // ──────────────────────────────────────────────────────
    public function changeRole(int $memberId, string $newRole): void
    {
        $actor  = Auth::user();
        $member = $this->getOrgMember($memberId);

        abort_unless(
            app(PermissionService::class)->can($actor, 'members.edit_role', $actor->currentOrganization),
            403
        );

        $actorMember = $actor->membershipIn();
        $newEnum     = OrganizationRole::from($newRole);

        if ($actorMember && $newEnum->rank() >= $actorMember->role->rank() && ! $actor->isPlatformAdmin()) {
            return;
        }

        $member->update(['role' => $newRole]);
        app(PermissionService::class)->invalidateCache($member->user_id, $actor->current_organization_id);
    }

    public function suspend(int $memberId): void
    {
        $this->setStatus($memberId, 'suspended', 'members.suspend');
    }

    public function reactivate(int $memberId): void
    {
        $this->setStatus($memberId, 'active', 'members.suspend');
    }

    public function remove(int $memberId): void
    {
        $this->setStatus($memberId, 'left', 'members.remove');
    }

    private function setStatus(int $memberId, string $status, string $perm): void
    {
        $actor  = Auth::user();
        $member = $this->getOrgMember($memberId);

        abort_unless(
            app(PermissionService::class)->can($actor, $perm, $actor->currentOrganization),
            403
        );

        if ($member->user_id === $actor->id) {
            return; // Ne pas se toucher soi-même
        }

        $member->update(['status' => $status]);
        app(PermissionService::class)->invalidateCache($member->user_id, $actor->current_organization_id);
    }

    // ──────────────────────────────────────────────────────
    // Permissions custom
    // ──────────────────────────────────────────────────────
    public function openPermissions(int $memberId): void
    {
        $this->editingMemberId = $memberId;
        $this->showPermissions = true;
    }

    public function togglePermission(string $perm, bool $value): void
    {
        $member = OrganizationMember::find($this->editingMemberId);

        if (! $member) return;

        $value ? $member->grantPermission($perm) : $member->revokePermission($perm);
    }

    // ──────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────
    private function getOrgMember(int $memberId): OrganizationMember
    {
        return OrganizationMember::where(
            'organization_account_id', Auth::user()->current_organization_id
        )->findOrFail($memberId);
    }

    public function render()
    {
        $permService = app(PermissionService::class);

        return view('livewire.provider-company.team-management', [
            'members'        => $this->membersProperty,
            'availableRoles' => $this->availableRolesProperty,
            'editingMember'  => $this->editingMemberProperty,
            'allPermissions' => $permService->allPermissionKeys(),
        ])->layout('layouts.provider-company');
    }
}
