<?php

namespace App\Livewire\ProviderCompany;

use App\Models\FieldTeam;
use App\Models\FieldTeamMember;
use App\Models\OrganizationMember;
use App\Models\ServiceZone;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LES ÉQUIPES TERRAIN D'UNE SOCIÉTÉ, PILOTÉES PAR CETTE SOCIÉTÉ.
 *
 * `FieldTeam` porte déjà tout le nécessaire — organisation, zone de service, chef d'équipe,
 * capacité maximale, statut — mais n'était manipulable que depuis les écrans d'ADMINISTRATION de
 * la plateforme et un écran employé. Une société voulant ouvrir une agence, la rattacher à une
 * zone ou en nommer le responsable devait passer par un administrateur.
 *
 * Les clés `team.view`, `team.create` et `team.manage` existaient elles aussi dans la matrice de
 * `PermissionService`, accordées à plusieurs rôles, sans qu'aucun code ne les consulte. Cet écran
 * les met en service : la capacité et le droit existaient, seule la porte manquait.
 */
class FieldTeams extends Component
{
    use EnforcesActiveOrgMembership;

    public string $nom = '';

    public ?int $zoneId = null;

    public ?int $chefId = null;

    public int $capaciteMax = 3;

    public function mount(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.view', $acteur->currentOrganization),
            403
        );
    }

    public function creer(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.create', $acteur->currentOrganization),
            403
        );

        $this->validate([
            'nom' => ['required', 'string', 'max:120'],
            'capaciteMax' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $orgId = $acteur->current_organization_id;

        /*
         * Zone et chef d'équipe viennent du navigateur : on ne les retient que s'ils appartiennent
         * bien à cette société. Sans cela, une agence pourrait nommer responsable l'employé d'une
         * autre entreprise.
         */
        $chefLegitime = $this->chefId !== null
            && OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('user_id', $this->chefId)
                ->where('status', 'active')
                ->exists();

        FieldTeam::create([
            'organization_account_id' => $orgId,
            'name' => $this->nom,
            'slug' => Str::slug($this->nom).'-'.Str::lower(Str::random(5)),
            'status' => 'active',
            'service_zone_id' => $this->zoneId,
            'team_lead_user_id' => $chefLegitime ? $this->chefId : null,
            'max_concurrent_missions' => $this->capaciteMax,
        ]);

        $this->reset(['nom', 'zoneId', 'chefId']);
        $this->capaciteMax = 3;
    }

    /**
     * L'équipe dont on regarde la composition.
     *
     * Publique parce que la vue en dépend, et `#[Locked]` parce qu'elle vient du navigateur : une
     * propriété publique Livewire est modifiable par `$set`. Ici elle ne porte pas de décision
     * d'autorisation — chaque action revérifie sa permission et rescope sa requête — mais la
     * verrouiller évite qu'un identifiant forgé désigne une équipe qu'on n'a pas ouverte.
     */
    #[Locked]
    public ?int $equipeOuverteId = null;

    public function ouvrirLaComposition(int $teamId): void
    {
        $this->equipeOuverteId = $this->equipeDeLaSociete($teamId)?->id;
    }

    /**
     * Ajouter quelqu'un à une équipe.
     *
     * `field_team_members` n'était manipulable que depuis l'administration de la PLATEFORME : une
     * société qui créait son équipe sur cet écran ne pouvait pas la peupler, et devait appeler un
     * administrateur pour y mettre quelqu'un. L'équipe restait donc vide, et une équipe vide ne peut
     * recevoir aucune mission.
     */
    public function ajouterMembre(int $teamId, int $userId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );

        $equipe = $this->equipeDeLaSociete($teamId);

        if ($equipe === null) {
            return;
        }

        // La cible doit être un membre ACTIF de la société : sans cette garde, une équipe pourrait
        // enrôler l'employé d'une entreprise concurrente.
        $estDeLaMaison = OrganizationMember::query()
            ->where('organization_account_id', $equipe->organization_account_id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $estDeLaMaison) {
            return;
        }

        /*
         * `updateOrCreate` sur (équipe, personne) : le geste est REJOUABLE. Quelqu'un qui avait
         * quitté l'équipe la réintègre par le même bouton, sans ligne en double — et `left_at` est
         * remis à null, sans quoi il resterait invisible des lectures.
         */
        FieldTeamMember::updateOrCreate(
            ['field_team_id' => $equipe->id, 'user_id' => $userId],
            ['is_active' => true, 'left_at' => null, 'joined_at' => now()],
        );
    }

    /**
     * Retirer quelqu'un d'une équipe.
     *
     * La ligne SURVIT — `is_active` à faux, `left_at` daté. L'historique d'une équipe doit pouvoir
     * dire qui en a fait partie : les missions passées portent son nom.
     */
    public function retirerMembre(int $teamId, int $userId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );

        $equipe = $this->equipeDeLaSociete($teamId);

        if ($equipe === null) {
            return;
        }

        FieldTeamMember::query()
            ->where('field_team_id', $equipe->id)
            ->where('user_id', $userId)
            ->update(['is_active' => false, 'left_at' => now()]);

        /*
         * LE MENEUR QUI PART CESSE DE MENER. Laisser `team_lead_user_id` désigner un partant
         * donnerait la mission au premier membre actif à l'assignation suivante — sans que rien ne
         * l'explique — et `ReassignmentPolicy` continuerait de lui accorder la main sur les missions
         * de l'équipe.
         */
        if ((int) $equipe->team_lead_user_id === $userId) {
            $equipe->update(['team_lead_user_id' => null]);
        }
    }

    /** Une équipe de l'organisation active, ou `null`. Le scoping fait partie de la requête. */
    private function equipeDeLaSociete(int $teamId): ?FieldTeam
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return FieldTeam::query()
            ->where('organization_account_id', $orgId)
            ->find($teamId);
    }

    public function archiver(int $teamId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'team.manage', $acteur->currentOrganization),
            403
        );

        // Scopé sur l'organisation active : un identifiant forgé ne doit pas atteindre l'équipe
        // d'une autre société.
        $equipe = FieldTeam::query()
            ->where('organization_account_id', $acteur->current_organization_id)
            ->find($teamId);

        if (! $equipe) {
            return;
        }

        $equipe->update(['status' => 'archived']);
    }

    public function render(): View
    {
        $orgId = Auth::user()->current_organization_id;

        return view('livewire.provider-company.field-teams', [
            'equipes' => FieldTeam::query()
                ->where('organization_account_id', $orgId)
                ->with([
                    'serviceZone:id,name',
                    'teamLead:id,name',
                    // La composition, sans les partis : une équipe garde ses lignes historiques.
                    'activeMembers.user:id,name',
                ])
                ->orderBy('name')
                ->get(),
            'zones' => ServiceZone::query()->orderBy('name')->get(['id', 'name']),
            'collegues' => OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('status', 'active')
                ->with('user:id,name')
                ->get(),
        ])->layout('layouts.provider-company');
    }
}
