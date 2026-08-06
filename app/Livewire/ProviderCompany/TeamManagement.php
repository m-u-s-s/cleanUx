<?php

namespace App\Livewire\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\OrganizationMembershipService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\Support\Livewire\Concerns\GuardsOrganizationMembers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * @property-read Collection<int, OrganizationMember> $members
 * @property-read array<string, mixed> $availableRoles
 * @property-read ?OrganizationMember $editingMember
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

    public string $activeTab = 'members'; // members | invitations | performance

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
            /*
             * TRI PORTABLE PLUTÔT QUE `FIELD()` (corrigé le 2026-08-05).
             *
             * `FIELD()` n'existe qu'en MySQL. La suite tournant sur SQLite, toute vérification
             * affichant ne serait-ce qu'un membre actif échouait sur « no such function: FIELD ».
             * La requête centrale de cet écran était donc intestable — le terrain exact sur lequel
             * les défauts corrigés dans ce lot ont pu survivre.
             *
             * `CASE` produit le même ordre sur les deux moteurs.
             */
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

    /**
     * Le membre affiché dans la modale de permissions.
     *
     * Scopé sur l'organisation active : sans cela, un identifiant étranger faisait afficher le
     * nom et la photo d'un membre d'une AUTRE société. Fermer l'écriture ne suffisait pas — une
     * fuite en lecture reste une fuite.
     */
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

        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteRole' => ['required'],
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

            /*
             * LE MEMBRE ET SON PROFIL PRESTATAIRE VONT ENSEMBLE (corrigé le 2026-08-05).
             *
             * On créait ici un `OrganizationMember` seul. `ProviderDashboard::mount()` exigeant
             * `isProviderCompanyWorker()`, l'employé rejoignait la société puis se heurtait à un
             * 403 sur son écran principal. Le service rend les deux écritures indissociables.
             */
            app(OrganizationMembershipService::class)->rattacher(
                $actor->currentOrganization,
                $targetUser,
                $this->inviteRole,
                $actor->id,
            );
        } else {
            /*
             * INVITER QUELQU'UN QUI N'A PAS ENCORE DE COMPTE (corrigé le 2026-08-05).
             *
             * Cette branche était un `// TODO` vide : aucun jeton, aucun email, aucune trace. Le
             * formulaire se réinitialisait ensuite comme dans le cas nominal, si bien que le
             * responsable croyait avoir invité quelqu'un qui n'avait jamais rien reçu.
             */
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
        $member = $this->getOrgMember($memberId);

        abort_unless(
            app(PermissionService::class)->can($actor, 'members.edit_role', $actor->currentOrganization),
            403
        );

        $actorMember = $actor->membershipIn();
        $newEnum = OrganizationRole::from($newRole);

        if ($actorMember && $newEnum->rank() >= $actorMember->role->rank() && ! $actor->isPlatformAdmin()) {
            return;
        }

        /*
         * LE DERNIER PROPRIÉTAIRE NE SE DÉCLASSE PAS.
         *
         * Une société sans propriétaire actif n'a plus personne pour inviter, facturer ou céder
         * ses droits : l'enfermement serait définitif, et irréparable sans intervention en base.
         * La garde porte sur le DERNIER, pas sur le rôle — tant qu'un autre owner actif existe,
         * le déclassement reste permis.
         */
        if ($newEnum !== OrganizationRole::OWNER && $this->estLeDernierProprietaire($member)) {
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
        $actor = Auth::user();
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

    /**
     * Accorder ou retirer une permission à un membre.
     *
     * L'identifiant vient du client : il est résolu par `memberSousGarde()`, qui le scope sur
     * l'organisation active, exige `members.manage_permissions` — réservée au propriétaire, car
     * distribuer des droits n'est pas inviter — et applique la hiérarchie. Un identifiant
     * étranger, un acteur sans le droit ou une cible de rang supérieur rendent simplement `null`,
     * sans rien divulguer.
     */
    public function togglePermission(string $perm, bool $value): void
    {
        $member = $this->memberSousGarde($this->editingMemberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member instanceof OrganizationMember) {
            return;
        }

        $value ? $member->grantPermission($perm) : $member->revokePermission($perm);

        app(PermissionService::class)->invalidateCache($member->user_id, $member->organization_account_id);
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
            'members' => $this->members,
            'availableRoles' => $this->availableRoles,
            'editingMember' => $this->editingMember,
            'allPermissions' => $permService->allPermissionKeys(),
        ])->layout('layouts.provider-company');
    }
}
