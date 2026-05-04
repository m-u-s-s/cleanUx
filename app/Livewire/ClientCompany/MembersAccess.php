<?php

namespace App\Livewire\ClientCompany;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class MembersAccess extends Component
{
    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool   $showInvite      = false;
    public bool   $showPermissions = false;
    public ?int   $editingMemberId = null;

    // Invitation
    public string $inviteEmail    = '';
    public string $inviteRole     = OrganizationRole::REQUESTER->value;
    public string $inviteMessage  = '';

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getMembersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->with('user:id,name,email,profile_photo_path')
            ->orderByRaw("FIELD(status, 'active', 'invited', 'suspended', 'left')")
            ->get();
    }

    public function getAvailableRolesProperty(): array
    {
        // Roles pour entreprise cliente
        return OrganizationRole::forClientCompany();
    }

    public function getEditingMemberProperty(): ?OrganizationMember
    {
        if (! $this->editingMemberId) {
            return null;
        }

        return OrganizationMember::with('user:id,name,email')
            ->find($this->editingMemberId);
    }

    // ──────────────────────────────────────────────────────
    // Invitation
    // ──────────────────────────────────────────────────────
    public function invite(): void
    {
        $user = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($user, 'members.invite', $user->currentOrganization),
            403
        );

        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteRole'  => ['required', 'in:' . implode(',',
                array_map(fn ($r) => $r->value, OrganizationRole::forClientCompany())
            )],
        ]);

        $orgId = $user->current_organization_id;

        // Vérifier si déjà membre
        $existingUser = User::where('email', $this->inviteEmail)->first();

        if ($existingUser) {
            $alreadyMember = OrganizationMember::where('organization_account_id', $orgId)
                ->where('user_id', $existingUser->id)
                ->where('status', '!=', 'left')
                ->exists();

            if ($alreadyMember) {
                $this->addError('inviteEmail', 'Cet utilisateur est déjà membre de l\'organisation.');
                return;
            }

            // Ajouter directement si l'utilisateur existe
            OrganizationMember::create([
                'organization_account_id' => $orgId,
                'user_id'                 => $existingUser->id,
                'role'                    => $this->inviteRole,
                'status'                  => 'active',
                'invited_by'              => $user->id,
                'invited_at'              => now(),
                'joined_at'               => now(),
            ]);
        } else {
            // Créer une invitation en attente (email à envoyer)
            // TODO: Envoyer l'email d'invitation avec lien
            // Mail::to($this->inviteEmail)->send(new OrganizationInvitation(...));
        }

        $this->inviteEmail   = '';
        $this->inviteMessage = '';
        $this->showInvite    = false;

        $this->dispatch('member-invited');
    }

    // ──────────────────────────────────────────────────────
    // Gestion des membres
    // ──────────────────────────────────────────────────────
    public function changeRole(int $memberId, string $newRole): void
    {
        $actor  = Auth::user();
        $orgId  = $actor->current_organization_id;
        $member = OrganizationMember::where('organization_account_id', $orgId)->findOrFail($memberId);

        abort_unless(
            app(PermissionService::class)->can($actor, 'members.edit_role', $actor->currentOrganization),
            403
        );

        $actorMember = $actor->membershipIn();

        // On ne peut pas promouvoir à un rang plus haut que le sien
        $newRoleEnum = OrganizationRole::from($newRole);
        if ($actorMember && $newRoleEnum->rank() >= $actorMember->role->rank() && ! $actor->isPlatformAdmin()) {
            $this->addError('role', 'Vous ne pouvez pas attribuer un rôle supérieur ou égal au vôtre.');
            return;
        }

        $member->update(['role' => $newRole]);

        // Invalider le cache des permissions
        app(PermissionService::class)->invalidateCache($member->user_id, $orgId);
    }

    public function suspend(int $memberId): void
    {
        $this->changeMemberStatus($memberId, 'suspended', 'members.suspend');
    }

    public function reactivate(int $memberId): void
    {
        $this->changeMemberStatus($memberId, 'active', 'members.suspend');
    }

    public function remove(int $memberId): void
    {
        $this->changeMemberStatus($memberId, 'left', 'members.remove');
    }

    private function changeMemberStatus(int $memberId, string $status, string $permission): void
    {
        $actor = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($actor, $permission, $actor->currentOrganization),
            403
        );

        $member = OrganizationMember::where('organization_account_id', $actor->current_organization_id)
            ->findOrFail($memberId);

        // Sécurité : ne pas se suspendre soi-même
        if ($member->user_id === $actor->id) {
            return;
        }

        // Sécurité : ne pas toucher à un membre de rang supérieur
        $actorMember  = $actor->membershipIn();
        $targetRole   = OrganizationRole::from($member->role->value);

        if ($actorMember && ! $actorMember->role->canManage($targetRole) && ! $actor->isPlatformAdmin()) {
            return;
        }

        $member->update(['status' => $status]);
        app(PermissionService::class)->invalidateCache($member->user_id, $actor->current_organization_id);
    }

    // ──────────────────────────────────────────────────────
    // Permissions personnalisées
    // ──────────────────────────────────────────────────────
    public function openPermissions(int $memberId): void
    {
        $this->editingMemberId = $memberId;
        $this->showPermissions = true;
    }

    public function toggleCustomPermission(string $permission, bool $value): void
    {
        if (! $this->editingMemberId) {
            return;
        }

        $member = OrganizationMember::find($this->editingMemberId);

        if (! $member) {
            return;
        }

        if ($value) {
            $member->grantPermission($permission);
        } else {
            $member->revokePermission($permission);
        }
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.client-company.members-access', [
            'members'        => $this->membersProperty,
            'availableRoles' => $this->availableRolesProperty,
            'editingMember'  => $this->editingMemberProperty,
            'allPermissions' => app(PermissionService::class)->allPermissionKeys(),
        ])->layout('layouts.client-company');
    }
}
