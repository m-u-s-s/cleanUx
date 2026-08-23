<?php

namespace App\Livewire\ProviderCompany;

use App\Models\FieldTeam;
use App\Models\OrganizationMember;
use App\Models\ProviderAgency;
use App\Models\ServiceZone;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LES IMPLANTATIONS DE LA SOCIÉTÉ — le dépôt de Bruxelles, l'antenne d'Anvers. */
class Agencies extends Component
{
    use EnforcesActiveOrgMembership;

    public string $nom = '';

    public string $ville = '';

    public string $adresse = '';

    public string $codePostal = '';

    public ?int $zoneId = null;

    /** L'implantation dont on regarde le rattachement. */
    #[Locked]
    public ?int $agenceOuverteId = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'agencies.view', $acteur->currentOrganization),
            403
        );
    }

    public function creer(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'agencies.manage', $acteur->currentOrganization),
            403
        );

        $this->validate([
            'nom' => ['required', 'string', 'max:120'],
            'ville' => ['nullable', 'string', 'max:120'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'codePostal' => ['nullable', 'string', 'max:20'],
        ]);

        ProviderAgency::create([
            'provider_organization_id' => $acteur->current_organization_id,
            'name' => $this->nom,
            // Unique PAR SOCIÉTÉ : deux prestataires peuvent appeler leur implantation « nord ».
            'slug' => Str::slug($this->nom).'-'.Str::lower(Str::random(5)),
            'address' => $this->adresse ?: null,
            'city' => $this->ville ?: null,
            'postal_code' => $this->codePostal ?: null,
            // La zone vient du navigateur : on ne la retient que si elle existe vraiment.
            'service_zone_id' => $this->zoneLegitime(),
            'status' => 'active',
        ]);

        $this->reset(['nom', 'ville', 'adresse', 'codePostal', 'zoneId']);
    }

    /** Archiver une implantation. */
    public function archiver(int $agencyId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'agencies.manage', $acteur->currentOrganization),
            403
        );

        $this->agenceDeLaSociete($agencyId)?->update(['status' => 'archived']);
    }

    public function reactiver(int $agencyId): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'agencies.manage', $acteur->currentOrganization),
            403
        );

        $this->agenceDeLaSociete($agencyId)?->update(['status' => 'active']);
    }

    public function ouvrirLeRattachement(int $agencyId): void
    {
        $this->agenceOuverteId = $this->agenceDeLaSociete($agencyId)?->id;
    }

    /** Rattacher une équipe terrain à une implantation, ou l'en détacher. */
    public function rattacherEquipe(int $agencyId, int $teamId, bool $detacher = false): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'agencies.manage', $acteur->currentOrganization),
            403
        );

        $agence = $this->agenceDeLaSociete($agencyId);

        if ($agence === null) {
            return;
        }

        // Scopé sur l'organisation : un identifiant forgé ne doit pas déplacer l'équipe d'un
        // concurrent sous notre enseigne.
        FieldTeam::query()
            ->where('organization_account_id', $acteur->current_organization_id)
            ->whereKey($teamId)
            ->update(['provider_agency_id' => $detacher ? null : $agence->id]);
    }

    /** Une agence de l'organisation active, ou `null`. Le scoping fait partie de la requête. */
    private function agenceDeLaSociete(int $agencyId): ?ProviderAgency
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return ProviderAgency::query()
            ->where('provider_organization_id', $orgId)
            ->find($agencyId);
    }

    private function zoneLegitime(): ?int
    {
        if ($this->zoneId === null) {
            return null;
        }

        return ServiceZone::query()->whereKey($this->zoneId)->exists() ? $this->zoneId : null;
    }

    public function render(): View
    {
        $orgId = Auth::user()->current_organization_id;

        return view('livewire.provider-company.agencies', [
            'agences' => ProviderAgency::query()
                ->where('provider_organization_id', $orgId)
                ->with(['serviceZone:id,name', 'fieldTeams:id,provider_agency_id,name'])
                ->orderBy('name')
                ->get(),
            'zones' => ServiceZone::query()->orderBy('name')->get(['id', 'name']),
            // Les équipes SANS implantation d'abord : ce sont celles qu'on vient rattacher.
            'equipes' => FieldTeam::query()
                ->where('organization_account_id', $orgId)
                ->orderByRaw('provider_agency_id is null desc')
                ->orderBy('name')
                ->get(['id', 'name', 'provider_agency_id']),
            'membres' => OrganizationMember::query()
                ->where('organization_account_id', $orgId)
                ->where('status', 'active')
                ->count(),
        ])->layout('layouts.provider-company');
    }
}
