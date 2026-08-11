<?php

namespace App\Livewire\ProviderCompany;

use App\Models\FleetCertification;
use App\Services\FleetV2\ProviderFleetService;
use App\Services\PermissionService;
use App\Services\Quality\WorkerQualityScoreService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LE SCORE QUALITÉ INTERNE (E26) ET LA FLOTTE DE LA SOCIÉTÉ (E27).
 *
 * DEUX MODULES SUR UN ÉCRAN PARCE QU'ILS RÉPONDENT À LA MÊME QUESTION : qui peut travailler demain,
 * et avec quoi. Une certification expirée refuse une assignation ; un score de ponctualité en chute
 * annonce le contrat qu'on va perdre. Les séparer obligerait à ouvrir deux écrans pour préparer une
 * seule conversation.
 *
 * LE SCORE NE SORT PAS DE LA SOCIÉTÉ. Il sert à repérer qui a besoin d'aide, pas à classer
 * publiquement : l'exposer côté client en ferait un outil de sélection, ce qu'aucun exécutant n'a
 * accepté en signant.
 *
 * ET UN SCORE SANS MATIÈRE NE SE FABRIQUE PAS. Sous trois missions, l'écran affiche « pas assez de
 * données » plutôt qu'un nombre qui serait lu comme un jugement.
 */
class QualityAndFleet extends Component
{
    use EnforcesActiveOrgMembership;

    #[Locked]
    public ?string $refus = null;

    public function mount(): void
    {
        $acteur = Auth::user();

        $permissions = app(PermissionService::class);

        // Deux portes pour un écran : le responsable qualité entre par `missions.quality`, le
        // gestionnaire de flotte par `fleet.view`. Exiger les deux fermerait l'écran à chacun.
        abort_unless(
            $permissions->can($acteur, 'missions.quality', $acteur->currentOrganization)
                || $permissions->can($acteur, 'fleet.view', $acteur->currentOrganization),
            403
        );
    }

    public function render(): View
    {
        $acteur = Auth::user();
        $orgId = (int) $acteur->current_organization_id;
        $permissions = app(PermissionService::class);

        $peutVoirLaQualite = $permissions->can($acteur, 'missions.quality', $acteur->currentOrganization);
        $peutVoirLaFlotte = $permissions->can($acteur, 'fleet.view', $acteur->currentOrganization);

        $flotte = app(ProviderFleetService::class);

        return view('livewire.provider-company.quality-and-fleet', [
            'scores' => $peutVoirLaQualite
                ? app(WorkerQualityScoreService::class)->pourLaSociete($orgId)
                : [],
            'peutVoirLaQualite' => $peutVoirLaQualite,
            'vehicules' => $peutVoirLaFlotte ? $flotte->vehicules($orgId) : collect(),
            'equipements' => $peutVoirLaFlotte ? $flotte->equipements($orgId) : collect(),
            // La SEULE lecture qui change quelque chose : elle évite qu'une assignation soit
            // refusée un matin sans que personne ne sache pourquoi.
            'echeances' => $peutVoirLaFlotte ? $flotte->echeances($orgId) : collect(),
            'peutVoirLaFlotte' => $peutVoirLaFlotte,
            'statutRevoque' => FleetCertification::STATUS_REVOKED,
            'missionsMinimum' => WorkerQualityScoreService::MISSIONS_MINIMUM,
        ])->layout('layouts.provider-company');
    }
}
