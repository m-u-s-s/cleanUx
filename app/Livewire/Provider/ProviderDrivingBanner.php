<?php

namespace App\Livewire\Provider;

use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\ConduiteRequirements;
use App\Support\Domain\TradeRouteRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** « VOUS NE RECEVEZ PLUS DE COURSES, ET VOICI POURQUOI. */
class ProviderDrivingBanner extends Component
{
    public function render(): View
    {
        $user = Auth::user();
        $regles = app(ConduiteRequirements::class);

        $alertes = [];

        if ($user) {
            $metiers = $user->trades()->with('questions')->get();

            foreach ($metiers as $metier) {
                if (! $regles->sappliqueA($metier)) {
                    continue;
                }

                $manquants = $this->manquantsMemeEnGrace($regles, $user, $metier);

                if ($manquants === []) {
                    continue;
                }

                $alertes[] = [
                    'metier' => $metier->name,
                    'manquants' => $manquants,
                    'bloquant_depuis' => $regles->bloquantDepuis($metier),
                    'deja_bloquant' => $regles->estBloquant($metier),
                ];
            }
        }

        return view('livewire.provider.provider-driving-banner', ['alertes' => $alertes]);
    }

    /**
     * Ce qui manque, MÊME PENDANT la période de grâce.
     *
     * @return list<string>
     */
    private function manquantsMemeEnGrace(ConduiteRequirements $regles, User $user, Trade $metier): array
    {
        // Un métier dont la règle n'est pas encore opposable rend `[]` : on interroge donc l'état
        // en supposant la règle active, ce que fait `manquantsPour` sur un métier déjà bloquant.
        if ($regles->estBloquant($metier)) {
            return $regles->manquantsPour($user, $metier);
        }

        $copie = $metier->replicate();
        $copie->setRelation('questions', $metier->questions);
        // Une date d'activation ancienne rend la règle opposable POUR CE CALCUL seulement : la
        // copie n'est jamais enregistrée, et le dispatch continue d'appliquer la vraie date.
        $copie->route_rules_since = TradeRouteRules::estUnTrajet($metier) ? now()->subCentury() : null;
        $copie->taxi_rules_since = $metier->taxi_rules ? now()->subCentury() : null;

        return $regles->manquantsPour($user, $copie);
    }
}
