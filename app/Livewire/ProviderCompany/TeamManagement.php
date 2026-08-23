<?php

namespace App\Livewire\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\MotifDeRefus;
use App\Services\Organizations\OrganizationMemberAdministration;
use App\Services\Organizations\OrganizationMembershipService;
use App\Services\Organizations\ResultatAdministration;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\Support\Livewire\Concerns\GuardsOrganizationMembers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read Collection<int, OrganizationMember> $members
 * @property-read array<string, mixed> $availableRoles
 * @property-read ?OrganizationMember $editingMember
 * @property-read Collection<int, OrganizationInvitation> $invitationsEnAttente
 */
class TeamManagement extends Component
{
    use EnforcesActiveOrgMembership;
    use GuardsOrganizationMembers;
    use WithPagination;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool $showInvite = false;

    public bool $showPermissions = false;

    public ?int $editingMemberId = null;

    public string $searchQuery = '';

    public string $filterRole = '';

    public string $filterStatus = 'active';

    /**
     * `performance` figurait ici sans que rien ne le rende ; il a été retiré plutôt que déclaré une fois de plus — un nom qui ne désigne rien est ce que ce dépôt corrige toute la journée.
     *
     * @var string members | invitations
     */
    public string $activeTab = 'members';

    // Formulaire invitation
    public string $inviteEmail = '';

    public string $inviteRole = OrganizationRole::WORKER->value;

    public string $inviteNote = '';

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
            ->when($this->searchQuery, fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$this->searchQuery}%")
                ->orWhere('email', 'like', "%{$this->searchQuery}%")
            )
            )
            ->with(['user:id,name,email,profile_photo_path', 'invitedBy:id,name'])
            // TRI PORTABLE PLUTÔT QUE `FIELD()` (corrigé le 2026-08-05).
            ->orderByRaw(
                'CASE role '
                ."WHEN 'owner' THEN 0 "
                ."WHEN 'operations_manager' THEN 1 "
                ."WHEN 'dispatcher' THEN 2 "
                ."WHEN 'team_lead' THEN 3 "
                ."WHEN 'quality_manager' THEN 4 "
                ."WHEN 'finance' THEN 5 "
                ."WHEN 'worker' THEN 6 "
                ."WHEN 'viewer' THEN 7 "
                .'ELSE 99 END'
            )
            ->get();
    }

    public function getAvailableRolesProperty(): array
    {
        return OrganizationRole::forProviderCompany();
    }

    /** Le membre affiché dans la modale de permissions. */
    public function getEditingMemberProperty(): ?OrganizationMember
    {
        if ($this->editingMemberId === null) {
            return null;
        }

        return OrganizationMember::query()
            ->where('organization_account_id', Auth::user()?->current_organization_id)
            ->with('user:id,name,email,profile_photo_path')
            ->find($this->editingMemberId);
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

        // `Rule::in` ET NON un simple `required`.
        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteRole' => ['required', Rule::in(array_map(fn ($role) => $role->value, $this->availableRoles))],
        ]);

        $orgId = $actor->current_organization_id;
        $targetUser = User::where('email', $this->inviteEmail)->first();

        // Vérifier hiérarchie
        $actorMember = $actor->membershipIn();
        $newRoleEnum = OrganizationRole::from($this->inviteRole);

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

            // LE MEMBRE ET SON PROFIL PRESTATAIRE VONT ENSEMBLE (corrigé le 2026-08-05).
            app(OrganizationMembershipService::class)->rattacher(
                $actor->currentOrganization,
                $targetUser,
                $this->inviteRole,
                $actor->id,
            );
        } else {
            // INVITER QUELQU'UN QUI N'A PAS ENCORE DE COMPTE (corrigé le 2026-08-05).
            $invitation = OrganizationInvitation::updateOrCreate(
                [
                    'organization_account_id' => $orgId,
                    'email' => $this->inviteEmail,
                    'status' => 'pending',
                ],
                [
                    'role' => $this->inviteRole,
                    'invited_by' => $actor->id,
                    'token' => OrganizationInvitation::genererJeton(),
                    'expires_at' => now()->addDays(14),
                ],
            );

            // Soft-fail : un incident d'envoi ne doit pas annuler l'invitation déjà enregistrée,
            // que l'on peut renvoyer. On trace pour ne pas perdre l'information.
            try {
                Mail::to($this->inviteEmail)->send(new OrganizationInvitationMail(
                    $invitation,
                    route('organization.invitations.accept', $invitation->token),
                ));
            } catch (\Throwable $e) {
                Log::warning('Envoi de l\'invitation impossible', [
                    'invitation_id' => $invitation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->reset(['inviteEmail', 'inviteRole', 'inviteNote', 'showInvite']);
    }

    // ──────────────────────────────────────────────────────
    // Gestion des membres
    // ──────────────────────────────────────────────────────
    public function changeRole(int $memberId, string $newRole): void
    {
        $actor = Auth::user();

        // LA GARDE EST BIDIRECTIONNELLE, ET ELLE NE L'ÉTAIT QU'À MOITIÉ.
        // LES RÈGLES SONT PARTIES DANS `OrganizationMemberAdministration`, pas les réponses.
        $resultat = app(OrganizationMemberAdministration::class)->changerLeRole(
            $actor,
            (int) $actor->current_organization_id,
            $memberId,
            $newRole,
        );

        $this->refuserSiIntrouvable($resultat, $memberId);
        abort_if($resultat->estRefuse(MotifDeRefus::PERMISSION), 403);
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
        $actor = Auth::user();

        // MÊME EXTRACTION, MÊME CONTRAT DE SURFACE.
        $resultat = app(OrganizationMemberAdministration::class)->changerLeStatut(
            $actor,
            (int) $actor->current_organization_id,
            $memberId,
            $status,
            $perm,
        );

        $this->refuserSiIntrouvable($resultat, $memberId);
        abort_if($resultat->estRefuse(MotifDeRefus::PERMISSION), 403);
        abort_if($resultat->estRefuse(MotifDeRefus::HIERARCHIE), 403);
    }

    /** L'identifiant étranger lève une `ModelNotFoundException`, PAS un 404 HTTP. */
    private function refuserSiIntrouvable(ResultatAdministration $resultat, int $memberId): void
    {
        if ($resultat->estRefuse(MotifDeRefus::INTROUVABLE)) {
            throw (new ModelNotFoundException)->setModel(OrganizationMember::class, [$memberId]);
        }
    }

    // ──────────────────────────────────────────────────────
    // Invitations en attente
    // ──────────────────────────────────────────────────────

    /**
     * Les invitations que la société a envoyées et qui n'ont pas encore abouti.
     *
     * @return Collection<int, OrganizationInvitation>
     */
    public function getInvitationsEnAttenteProperty(): Collection
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return OrganizationInvitation::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationInvitation::query()
            ->where('organization_account_id', $orgId)
            ->where('status', 'pending')
            ->with('inviter:id,name')
            ->orderByDesc('id')
            ->get();
    }

    /** Révoquer une invitation — une erreur de frappe, un recrutement annulé. */
    public function revoquerInvitation(int $invitationId): void
    {
        $user = Auth::user();
        $orgId = $user?->current_organization_id;

        abort_if($orgId === null, 403);

        abort_unless(
            app(PermissionService::class)->can($user, 'members.invite', $user->currentOrganization),
            403
        );

        // Scoping DANS la requête : une invitation d'une autre société n'est jamais chargée.
        OrganizationInvitation::query()
            ->where('organization_account_id', $orgId)
            ->where('status', 'pending')
            ->whereKey($invitationId)
            ->update(['status' => 'revoked']);
    }

    // ──────────────────────────────────────────────────────
    // Permissions custom
    // ──────────────────────────────────────────────────────
    public function openPermissions(int $memberId): void
    {
        $this->editingMemberId = $memberId;
        $this->showPermissions = true;
    }

    /** Accorder ou retirer une permission à un membre. */
    public function togglePermission(string $perm, bool $value): void
    {
        $member = $this->memberSousGarde($this->editingMemberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member instanceof OrganizationMember) {
            return;
        }

        $value ? $member->grantPermission($perm) : $member->revokePermission($perm);

        app(PermissionService::class)->invalidateCache($member->user_id, $member->organization_account_id);
    }

    /** REMETTRE UN MEMBRE « COMME LES AUTRES ». */
    public function resetPermissions(): void
    {
        $member = $this->memberSousGarde($this->editingMemberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member instanceof OrganizationMember) {
            return;
        }

        $member->update(['permissions' => []]);

        app(PermissionService::class)->invalidateCache($member->user_id, $member->organization_account_id);
    }

    public function render()
    {
        $permService = app(PermissionService::class);

        return view('livewire.provider-company.team-management', [
            'members' => $this->members,
            'availableRoles' => $this->availableRoles,
            'editingMember' => $this->editingMember,
            'allPermissions' => $permService->allPermissionKeys(),
            'invitationsEnAttente' => $this->invitationsEnAttente,
        ])->layout('layouts.provider-company');
    }
}
