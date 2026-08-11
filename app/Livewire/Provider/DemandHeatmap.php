<?php

namespace App\Livewire\Provider;

use App\Models\Trade;
use App\Services\Provider\DemandHeatmapService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * OÙ ME PLACER, ET À QUELLE HEURE (E12).
 *
 * LA QUESTION QUE SE POSE TOUT INDÉPENDANT le matin, et à laquelle rien ne répond : il se place où
 * il s'est placé hier, et découvre après trois heures d'attente qu'il fallait être ailleurs. La
 * plateforme, elle, SAIT — les recherches de dispatch sont horodatées et géolocalisées.
 *
 * CE N'EST PAS UNE PROMESSE, et l'écran le dit. Une demande passée ne garantit pas une demande
 * future : afficher un classement sans dire sur combien de jours il porte ferait lire un pic isolé
 * comme une tendance, et déplacer quelqu'un pour rien.
 */
class DemandHeatmap extends Component
{
    /** Le métier filtré, ou tous. */
    public ?int $tradeId = null;

    /** La fenêtre d'observation, en jours. */
    public int $jours = 28;

    public function render(): View
    {
        // Bornée : une fenêtre venue du navigateur ne doit pas faire scanner deux ans de
        // recherches à chaque rendu.
        $jours = max(7, min(90, $this->jours));

        return view('livewire.provider.demand-heatmap', [
            'lignes' => app(DemandHeatmapService::class)->pourLaPeriode(
                Carbon::now()->subDays($jours),
                Carbon::now(),
                $this->tradeId,
            ),
            'metiers' => Trade::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'jours' => $jours,
        ])->layout('layouts.app');
    }
}
