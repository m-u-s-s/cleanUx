<?php

namespace App\Livewire\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Channel;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organizations\OrganizationMembershipService;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use App\Support\Livewire\Concerns\GuardsOrganizationMembers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
     * @var string members | invitations
     *
     * `performance` figurait ici sans que rien ne le rende ; il a été retiré plutôt que déclaré une
     * fois de plus — un nom qui ne désigne rien est ce que ce dépôt corrige toute la journée.
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

        /*
         * LA GARDE EST BIDIRECTIONNELLE, ET ELLE NE L'ÉTAIT QU'À MOITIÉ.
         *
         * Cette méthode chargeait sa cible par `getOrgMember()` — scoping sur l'organisation, et
         * rien de plus — puis ne comparait que le rang du NOUVEAU rôle à celui de l'acteur. Le rang
         * ACTUEL de la personne visée n'entrait jamais dans l'équation.
         *
         * Conséquence : un responsable d'exploitation à qui l'on accordait `members.edit_role`
         * pouvait déclasser un PROPRIÉTAIRE en nettoyeur. Le rôle visé étant de rang inférieur au
         * sien, la condition passait sans broncher, et la garde « dernier propriétaire » ne
         * couvrait pas le cas où la société en compte deux.
         *
         * `memberSousGarde()` faisait déjà exactement ce contrôle — `canManageMember()`, le rang de
         * l'acteur contre celui de la CIBLE — et cette méthode ne l'appelait pas. Elle l'appelle.
         */
        $member = $this->getOrgMember($memberId);

        abort_unless(
            app(PermissionService::class)->can($actor, 'members.edit_role', $actor->currentOrganization),
            403
        );

        $actorMember = $actor->membershipIn();
        $newEnum = OrganizationRole::from($newRole);

        /*
         * PREMIER VERSANT — LE RANG ACTUEL DE LA CIBLE, qui n'était pas regardé.
         *
         * Un responsable d'exploitation à qui l'on accordait `members.edit_role` pouvait déclasser
         * un PROPRIÉTAIRE en nettoyeur : seul le rang du NOUVEAU rôle était comparé, et il est
         * évidemment inférieur au sien. La garde « dernier propriétaire » ne couvrait pas le cas
         * d'une société qui en compte deux.
         *
         * La comparaison est `<`, PAS `<=`, et c'est délibéré. `canManageMember()` exige un rang
         * STRICTEMENT supérieur ; l'employer ici aurait interdit à un propriétaire de déclasser son
         * co-propriétaire — un comportement que ce dépôt autorise sciemment, testé et commenté :
         * « la protection porte sur le DERNIER, pas sur le rôle ». Fermer l'escalade ne doit pas
         * emporter une décision déjà prise pour de bonnes raisons.
         */
        if ($actorMember && $actorMember->role->rank() < $member->role->rank() && ! $actor->isPlatformAdmin()) {
            return;
        }

        // Second versant : on ne promeut personne au-dessus de soi. Sans lui, distribuer un rôle
        // deviendrait un moyen de se donner un supérieur complaisant.
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

        /*
         * DEUX GARDES MANQUAIENT ICI (ajoutées le 2026-08-06).
         *
         * Vérification faite, cette méthode possédait DÉJÀ le scoping sur l'organisation
         * (`getOrgMember()`), le contrôle de permission et le refus de l'auto-action. Il lui
         * manquait exactement ce que son équivalent client possédait :
         *
         *   1. HIÉRARCHIE — rien ne vérifiait l'autorité sur la PERSONNE visée. Un responsable
         *      d'exploitation à qui l'on accordait `members.suspend` pouvait suspendre le
         *      propriétaire de la société. `memberSousGarde()` ferme ce passage.
         *   2. DERNIER PROPRIÉTAIRE — plus bas.
         *
         * C'est la symétrie inverse de la phase 0, où c'était le client qui manquait ce que le
         * prestataire avait : aucun des deux écrans n'est « la bonne version » de l'autre.
         */
        $member = $this->memberSousGarde($memberId, $perm);

        if (! $member) {
            return;
        }

        if ($member->user_id === $actor->id) {
            return; // Ne pas se toucher soi-même
        }

        /*
         * Suspendre ou retirer le dernier propriétaire actif laisse la société sans personne pour
         * gérer ses accès, sa facturation ou ses employés — et aucun écran ne permet d'en nommer
         * un nouveau depuis l'extérieur. L'enfermement serait définitif.
         */
        if ($status !== 'active' && $this->estLeDernierProprietaire($member)) {
            return;
        }

        $member->update(['status' => $status]);
        app(PermissionService::class)->invalidateCache($member->user_id, $actor->current_organization_id);

        /*
         * UN DÉPART NE SE CONTENTE PAS DE CHANGER UN STATUT.
         *
         * `remove()` posait `left` et s'arrêtait là. Les missions de la semaine suivante restaient
         * assignées à quelqu'un qui ne viendra pas : le répartiteur les voyait « couvertes », et le
         * client découvrait l'absence le jour même. La personne restait aussi dans les canaux
         * d'équipe — et l'autorisation Reverb, qui vérifie l'appartenance au canal, lui donnait
         * accès aux échanges de son ancien employeur en temps réel.
         *
         * On ne défait QUE l'à-venir. Le passé dit qui a réalisé quoi : le réécrire fausserait la
         * facturation, les évaluations client et toute réclamation ultérieure.
         */
        if ($status === 'left') {
            $this->libererLAvenir($member->user_id, (int) $actor->current_organization_id);
        }
    }

    // ──────────────────────────────────────────────────────
    // Invitations en attente
    // ──────────────────────────────────────────────────────

    /**
     * Les invitations que la société a envoyées et qui n'ont pas encore abouti.
     *
     * `$activeTab` déclare `members | invitations | performance` depuis l'origine, et la vue n'en
     * rendait qu'un : une invitation partait dans le vide, sans qu'aucun écran ne dise à qui, ni si
     * elle avait expiré. Le seul recours était de réinviter, ce qui n'apprenait rien de plus.
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

    /**
     * Révoquer une invitation — une erreur de frappe, un recrutement annulé.
     *
     * Le statut change, LA LIGNE SURVIT : le jeton doit rester connu pour être refusé si quelqu'un
     * ouvre le lien après coup. Supprimer la ligne rendrait le jeton inconnu, et un jeton inconnu
     * ne se distingue pas d'un jeton jamais émis — le lien redeviendrait une porte ouverte selon la
     * façon dont l'écran d'acceptation traite l'introuvable.
     */
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

    /**
     * REMETTRE UN MEMBRE « COMME LES AUTRES ».
     *
     * Le seul geste disponible était d'inverser le booléen — ce qui n'efface rien : cela écrit une
     * SECONDE dérogation, l'inverse de la première. Or l'étage 1 de la résolution est prioritaire
     * sur la matrice de la société : un membre « remis à zéro » de cette façon gardait une ligne
     * figée, et cessait de suivre les réglages de son rôle. Le patron modifiait la matrice, et rien
     * ne bougeait pour cette personne, sans que l'écran ne l'explique.
     *
     * On vide donc réellement, plutôt que de superposer. Même garde que `togglePermission()` :
     * l'identifiant vient du client, `memberSousGarde()` le scope sur l'organisation active, exige
     * `members.manage_permissions` et applique la hiérarchie.
     */
    public function resetPermissions(): void
    {
        $member = $this->memberSousGarde($this->editingMemberId, 'members.manage_permissions', silencieuxSiIntrouvable: true);

        if (! $member instanceof OrganizationMember) {
            return;
        }

        $member->update(['permissions' => []]);

        app(PermissionService::class)->invalidateCache($member->user_id, $member->organization_account_id);
    }

    /**
     * Défaire ce qui n'a pas encore eu lieu, et rien d'autre.
     *
     * Les missions PASSÉES gardent leur intervenant : c'est l'historique de la société, et la
     * facturation comme les réclamations s'y appuient. Seules celles à venir retournent au dispatch.
     *
     * Les MESSAGES restent, eux aussi. Retirer quelqu'un d'un canal ne doit pas trouer la
     * conversation des autres — un fil dont la moitié disparaît devient illisible pour ceux qui
     * restent.
     */
    private function libererLAvenir(int $userId, int $orgId): void
    {
        $missionsAVenir = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->where('planned_start_at', '>', now())
            ->pluck('id');

        if ($missionsAVenir->isNotEmpty()) {
            MissionAssignment::query()
                ->whereIn('mission_id', $missionsAVenir)
                ->where('user_id', $userId)
                ->where('assignment_status', 'assigned')
                ->update(['assignment_status' => 'released']);

            // `lead_provider_user_id` est lu par le tableau de bord, l'autorisation Reverb
            // `mission.{id}` et le suivi de trajet : le laisser en place ferait viser les trois
            // sur quelqu'un qui ne viendra pas.
            Mission::query()
                ->whereIn('id', $missionsAVenir)
                ->where('lead_provider_user_id', $userId)
                ->update(['lead_provider_user_id' => null, 'status' => 'pending']);
        }

        $canaux = Channel::query()
            ->where('organization_account_id', $orgId)
            ->pluck('id');

        if ($canaux->isNotEmpty()) {
            DB::table('channel_members')
                ->whereIn('channel_id', $canaux)
                ->where('user_id', $userId)
                ->delete();
        }
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
            'invitationsEnAttente' => $this->invitationsEnAttente,
        ])->layout('layouts.provider-company');
    }
}
