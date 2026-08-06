<?php

namespace App\Livewire\ProviderCompany;

use App\Models\FieldTeam;
use App\Models\OrganizationMember;
use App\Models\ServiceZone;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
                ->with(['serviceZone:id,name', 'teamLead:id,name'])
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
