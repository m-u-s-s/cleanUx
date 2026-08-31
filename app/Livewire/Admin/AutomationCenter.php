<?php

namespace App\Livewire\Admin;

use App\Models\AutomationRule;
use App\Services\Automation\Catalogue;
use App\Services\FeatureFlag\FeatureFlagService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** La liste des regles : ce qu'elles font, leur etat, et si le moteur agit vraiment. */
class AutomationCenter extends Component
{
    public function render(Catalogue $catalogue, FeatureFlagService $drapeaux): View
    {
        $depuis = now()->subDays(7);

        $regles = AutomationRule::query()
            ->withCount(['actionsPosees as actions_sept_jours' => fn ($query) => $query->where('pose_le', '>=', $depuis)])
            ->orderBy('nom')
            ->get();

        return view('livewire.admin.automation-center', [
            'regles' => $regles,
            'declencheurs' => $catalogue->declencheurs(),
            'moteurActif' => $drapeaux->isEnabled('automation'),
        ]);
    }

    /** Cinq etats fixes du modele — pas un catalogue extensible, un match direct est legitime. */
    public function libelleEtat(string $etat): string
    {
        return match ($etat) {
            AutomationRule::ETAT_BROUILLON => 'Brouillon',
            AutomationRule::ETAT_OBSERVATION => 'Observation',
            AutomationRule::ETAT_ARMEE => 'Armée',
            AutomationRule::ETAT_SUSPENDUE => 'Suspendue',
            AutomationRule::ETAT_DESACTIVEE => 'Désactivée',
            default => $etat,
        };
    }

    /** Le jeton de teinte du systeme (`.brio-teinte`), jamais une couleur Tailwind litterale. */
    public function teinteEtat(string $etat): string
    {
        return match ($etat) {
            AutomationRule::ETAT_ARMEE => 'var(--brio-success)',
            AutomationRule::ETAT_OBSERVATION => 'var(--brio-info)',
            AutomationRule::ETAT_SUSPENDUE => 'var(--brio-warning)',
            AutomationRule::ETAT_DESACTIVEE => 'var(--brio-danger)',
            default => 'var(--brio-muted)',
        };
    }
}
