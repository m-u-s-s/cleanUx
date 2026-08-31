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

    public function classeEtat(string $etat): string
    {
        return match ($etat) {
            AutomationRule::ETAT_ARMEE => '!border-emerald-200 !bg-emerald-50 !text-emerald-700',
            AutomationRule::ETAT_OBSERVATION => '!border-sky-200 !bg-sky-50 !text-sky-700',
            AutomationRule::ETAT_SUSPENDUE => '!border-amber-200 !bg-amber-50 !text-amber-700',
            AutomationRule::ETAT_DESACTIVEE => '!border-rose-200 !bg-rose-50 !text-rose-700',
            default => '',
        };
    }
}
