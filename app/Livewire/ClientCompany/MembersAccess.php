<?php

namespace App\Livewire\ClientCompany;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\User;
use App\Services\Enterprise\MemberSiteAccessService;
use App\Services\Organizations\OrganizationMembershipService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\Support\Livewire\Concerns\GuardsOrganizationMembers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property-read Collection<int, OrganizationMember> $members
 * @property-read array<string, mixed> $availableRoles
 * @property-read ?OrganizationMember $editingMember
 */
class MembersAccess extends Component
{
    use EnforcesActiveOrgMembership;
    use GuardsOrganizationMembers;

    // ──────────────────────────────────────────────────────
    // State
    // ──────────────────────────────────────────────────────
    public bool $showInvite = false;

    public bool $showPermissions = false;

    public ?int $editingMemberId = null;

    /**
     * Les locaux cochés dans le panneau d'accès du membre en cours d'édition.
     *
     * @var array<int, int>
     */
    public array $sitesAutorises = [];

    // Invitation
    public string $inviteEmail = '';

    public string $inviteRole = OrganizationRole::REQUESTER->value;

    public string $inviteMessage = '';

    // ──────────────────────────────────────────────────────
    // Mount
    // ──────────────────────────────────────────────────────
    public function mount(): void
    {
        abort_unless(Auth::user()?->isClientCompany(), 403);
    }

    // ──────────────────────────────────────────────────────
    // Computed
    // ──────────────────────────────────────────────────────
    public function getMembersProperty()
    {
        $orgId = Auth::user()->current_organization_id;

        return OrganizationMember::where('organization_account_id', $orgId)
            ->with('user:id,name,email,profile_photo_path')
            // `FIELD()` est propre à MySQL : sous SQLite (la suite de tests) il fait échouer la
            // requête. `CASE` donne le même ordre sur les deux moteurs. Voir TeamManagement.
            ->orderByRaw(
                'CASE status '
                ."WHEN 'active' THEN 0 "
                ."WHEN 'invited' THEN 1 "
                ."WHEN 'suspended' THEN 2 "
                ."WHEN 'left' THEN 3 "
                .'ELSE 99 END'
            )
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

        return OrganizationMember::where('organization_account_id', Auth::user()->current_organization_id)
            ->with('user:id,name,email')
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
            'inviteRole' => ['required', 'in:'.implode(',',
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
            app(OrganizationMembershipService::class)->rattacher(
                $user->currentOrganization,
                $existingUser,
                $this->inviteRole,
                $user->id,
            );
        } else {
            /*
             * MÊME TROU QUE CÔTÉ PRESTATAIRE (corrigé le 2026-08-05).
             *
             * Cette branche n'était qu'un commentaire : inviter une adresse sans compte ne créait
             * rien et n'envoyait rien, tandis que le formulaire se vidait comme si l'invitation
             * était partie. On réutilise l'infrastructure d'invitation construite pour les
             * sociétés prestataires — le mécanisme est identique, seul le rôle diffère.
             */
            $invitation = OrganizationInvitation::updateOrCreate(
                [
                    'organization_account_id' => $orgId,
                    'email' => $this->inviteEmail,
                    'status' => 'pending',
                ],
                [
                    'role' => $this->inviteRole,
                    'invited_by' => $user->id,
                    'token' => OrganizationInvitation::genererJeton(),
                    'expires_at' => now()->addDays(14),
                ],
            );

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

        $this->inviteEmail = '';
        $this->inviteMessage = '';
        $this->showInvite = false;

        $this->dispatch('member-invited');
    }

    // ──────────────────────────────────────────────────────
    // Gestion des membres
    // ──────────────────────────────────────────────────────
    public function changeRole(int $memberId, string $newRole): void
    {
        $actor = Auth::user();
        $orgId = $actor->current_organization_id;

        /*
         * LA GARDE PORTAIT SUR LE RÔLE VISÉ, JAMAIS SUR LA CIBLE (corrigé le 2026-08-05).
         *
         * Le contrôle ci-dessous vérifiait qu'on n'attribue pas un rang supérieur au sien — ce qui
         * empêche bien l'escalade. Mais rien ne vérifiait qu'on a autorité sur la PERSONNE
         * modifiée : rétrograder le propriétaire en `viewer` passait sans obstacle, le rang visé
         * étant bas. `memberSousGarde()` ajoute cette vérification hiérarchique.
         *
         * À noter, contrairement à son équivalent prestataire : cet écran était déjà correctement
         * limité à l'organisation active et déjà gardé par une permission. Seules la hiérarchie et
         * la protection du dernier propriétaire manquaient.
         */
        $member = $this->memberSousGarde($memberId, 'members.edit_role');

        if (! $member) {
            return;
        }

        $actorMember = $actor->membershipIn();

        // On ne peut pas promouvoir à un rang plus haut que le sien
        $newRoleEnum = OrganizationRole::from($newRole);
        if ($actorMember && $newRoleEnum->rank() >= $actorMember->role->rank() && ! $actor->isPlatformAdmin()) {
            $this->addError('role', 'Vous ne pouvez pas attribuer un rôle supérieur ou égal au vôtre.');

            return;
        }

        /*
         * Une organisation sans propriétaire n'a plus personne pour gérer ses membres, ses accès
         * ni sa facturation — et aucun écran ne permet d'en renommer un. On refuse donc de
         * rétrograder le dernier.
         */
        if ($newRoleEnum !== OrganizationRole::OWNER && $this->estLeDernierProprietaire($member)) {
            $this->addError('role', 'Impossible de rétrograder le dernier propriétaire de l\'organisation.');

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
        $actorMember = $actor->membershipIn();
        $targetRole = OrganizationRole::from($member->role->value);

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

        /*
         * ACCORDER UNE PERMISSION N'EST PAS CHANGER UN RÔLE (corrigé le 2026-08-05).
         *
         * Ce point d'entrée était gardé par `members.edit_role`, la clé du changement de rôle.
         * Or il permet d'accorder n'importe quelle permission unitaire, y compris à soi-même :
         * il mérite sa propre clé, plus restrictive, et le contrôle hiérarchique sur la cible.
         */
        $member = $this->memberSousGarde($this->editingMemberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member) {
            return;
        }

        if ($value) {
            $member->grantPermission($permission);
        } else {
            $member->revokePermission($permission);
        }
    }

    /**
     * RESTREINDRE UN MEMBRE À SES LOCAUX — la table dormait, l'écran manquait.
     *
     * `organization_member_site_access` existait avec sa relation `authorizedMembers()`, et rien ne
     * l'écrivait. Conséquence pour l'utilisatrice : un responsable de site voyait TOUS les locaux
     * de sa société, avec les réservations, les adresses et les factures des autres agences.
     *
     * UN TABLEAU VIDE LÈVE LA RESTRICTION, il ne la durcit pas. C'est la lecture qui compte : « je
     * n'ai coché aucun local » veut dire « pas de restriction », pas « aucun accès ». L'inverse
     * viderait l'écran du membre au premier enregistrement par mégarde.
     */
    public function enregistrerLesSites(int $memberId): void
    {
        $member = $this->memberSousGarde($memberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member) {
            return;
        }

        // Les identifiants viennent du navigateur : on ne garde que des locaux de CETTE société,
        // sinon on restreindrait un membre à l'agence d'une entreprise voisine.
        $sitesDeLOrganisation = OrganizationSite::query()
            ->where('organization_account_id', $member->organization_account_id)
            ->pluck('id')
            ->all();

        $retenus = array_values(array_intersect(
            array_map('intval', $this->sitesAutorises),
            array_map('intval', $sitesDeLOrganisation),
        ));

        app(MemberSiteAccessService::class)->definirLesSites($member, $retenus);

        $this->dispatch('toast', $retenus === []
            ? 'Ce membre voit désormais tous les locaux.'
            : 'Accès enregistré : '.count($retenus).' local(aux).');
    }

    /**
     * Ouvrir le panneau d'accès avec les locaux déjà cochés.
     */
    public function ouvrirLesSites(int $memberId): void
    {
        $member = $this->memberSousGarde($memberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member) {
            return;
        }

        $this->editingMemberId = $memberId;
        $this->sitesAutorises = $member->user
            ? (app(MemberSiteAccessService::class)->sitesAutorises($member->user) ?? [])
            : [];
    }

    /**
     * Les locaux de la société, pour cocher.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrganizationSite>
     */
    #[Computed]
    public function sitesDeLaSociete(): \Illuminate\Database\Eloquent\Collection
    {
        return OrganizationSite::query()
            ->where('organization_account_id', Auth::user()->organization_account_id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    public function render()
    {
        return view('livewire.client-company.members-access', [
            'members' => $this->members,
            'availableRoles' => $this->availableRoles,
            'editingMember' => $this->editingMember,
            'allPermissions' => app(PermissionService::class)->allPermissionKeys(),
        ])->layout('layouts.client-company');
    }
}
