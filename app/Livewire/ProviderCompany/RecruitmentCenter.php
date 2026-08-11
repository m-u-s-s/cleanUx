<?php

namespace App\Livewire\ProviderCompany;

use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Trade;
use App\Services\PermissionService;
use App\Services\Recruitment\RecruitmentService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE RECRUTEMENT (E25).
 *
 * LA DERNIÈRE ÉTAPE EXISTAIT DÉJÀ. L'invitation à jeton conclut le recrutement depuis longtemps —
 * compte, adhésion, rôle. Ce qui manquait est AVANT : l'offre, les candidatures, le tri. Une société
 * publiait donc son annonce ailleurs, échangeait des courriels, puis revenait ici inviter quelqu'un
 * dont la plateforme n'avait jamais entendu parler. Impossible de savoir quelle annonce avait
 * produit quelle recrue.
 *
 * EMBAUCHER ÉMET L'INVITATION — un même bouton, pas deux écrans. Séparer les deux produirait le
 * défaut exact qu'on répare : une candidature marquée « embauché » et personne dans l'organigramme.
 *
 * DES DONNÉES DE GENS QUI NE SONT PAS DE LA MAISON. Un candidat confie son nom, son courriel et son
 * téléphone à une entreprise, pas à ses employés : `recruitment.view` n'est accordée qu'aux rôles
 * qui décident.
 */
class RecruitmentCenter extends Component
{
    use EnforcesActiveOrgMembership;

    public string $titre = '';

    public ?int $tradeId = null;

    public string $description = '';

    #[Locked]
    public ?int $offreOuverteId = null;

    #[Locked]
    public ?string $refus = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'recruitment.view', $acteur->currentOrganization),
            403
        );
    }

    public function ouvrirUneOffre(): void
    {
        $this->autoriserLaGestion();

        $this->validate([
            'titre' => ['required', 'string', 'max:160'],
        ]);

        $offre = app(RecruitmentService::class)->ouvrirUneOffre(
            (int) Auth::user()->current_organization_id,
            Auth::user(),
            $this->titre,
            $this->tradeId,
            $this->description !== '' ? $this->description : null,
        );

        $this->offreOuverteId = $offre->id;
        $this->reset(['titre', 'description', 'refus']);
    }

    public function consulterLOffre(int $offreId): void
    {
        $this->offreOuverteId = $this->offreDeLaSociete($offreId)?->id;
    }

    public function publier(int $offreId): void
    {
        $this->autoriserLaGestion();

        $offre = $this->offreDeLaSociete($offreId);

        if ($offre === null) {
            return;
        }

        try {
            app(RecruitmentService::class)->publier($offre);
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    public function fermer(int $offreId): void
    {
        $this->autoriserLaGestion();

        $offre = $this->offreDeLaSociete($offreId);

        if ($offre !== null) {
            app(RecruitmentService::class)->fermer($offre);
        }
    }

    public function statuerSurLaCandidature(int $candidatureId, string $decision): void
    {
        $this->autoriserLaGestion();

        $candidature = $this->candidatureDeLaSociete($candidatureId);

        if ($candidature === null) {
            return;
        }

        $service = app(RecruitmentService::class);

        try {
            match ($decision) {
                'shortlist' => $service->retenir($candidature, Auth::user()),
                // Embaucher ÉMET l'invitation : c'est elle, et elle seule, qui crée le collègue.
                'hire' => $service->embaucher($candidature, Auth::user()),
                default => $service->refuser($candidature, Auth::user()),
            };

            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    private function offreDeLaSociete(int $offreId): ?JobPosting
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return JobPosting::query()
            ->where('organization_account_id', $orgId)
            ->find($offreId);
    }

    private function candidatureDeLaSociete(int $candidatureId): ?JobApplication
    {
        $orgId = Auth::user()?->current_organization_id;

        if (! $orgId) {
            return null;
        }

        /*
         * Le scoping passe par l'OFFRE : une candidature ne porte pas d'organisation, et charger
         * par son seul identifiant exposerait celles d'une autre société — c'est-à-dire des données
         * personnelles de gens qui n'ont postulé nulle part ici.
         */
        return JobApplication::query()
            ->whereHas('posting', fn ($q) => $q->where('organization_account_id', $orgId))
            ->find($candidatureId);
    }

    private function autoriserLaGestion(): void
    {
        $acteur = Auth::user();

        abort_unless(
            app(PermissionService::class)->can($acteur, 'recruitment.manage', $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $orgId = (int) Auth::user()->current_organization_id;

        return view('livewire.provider-company.recruitment-center', [
            'offres' => JobPosting::query()
                ->where('organization_account_id', $orgId)
                ->withCount('applications')
                ->with('trade:id,name')
                ->latest()
                ->get(),
            'offreOuverte' => $this->offreOuverteId
                ? JobPosting::query()
                    ->where('organization_account_id', $orgId)
                    ->with('applications')
                    ->find($this->offreOuverteId)
                : null,
            'metiers' => Trade::query()->orderBy('name')->get(['id', 'name']),
            'peutGerer' => app(PermissionService::class)
                ->can(Auth::user(), 'recruitment.manage', Auth::user()->currentOrganization),
        ])->layout('layouts.provider-company');
    }
}
