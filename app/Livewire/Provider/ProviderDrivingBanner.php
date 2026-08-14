<?php

namespace App\Livewire\Provider;

use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\ConduiteRequirements;
use App\Support\Domain\TradeRouteRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * « VOUS NE RECEVEZ PLUS DE COURSES, ET VOICI POURQUOI. »
 *
 * L'angle mort de cette plateforme est le compte ACTIF mais NON VÉRIFIÉ : l'application s'ouvre
 * normalement, tout paraît en ordre, et le téléphone cesse simplement de sonner. Personne ne fait
 * le lien — ni le prestataire, qui croit à un creux d'activité, ni le support, qui reçoit un appel
 * trois semaines plus tard.
 *
 * Ce bandeau ferme cet écart pour les exigences de conduite. Il nomme la pièce qui manque, le métier
 * concerné, et la date à laquelle ça devient bloquant — avant qu'elle arrive, pas après.
 *
 * IL NE S'AFFICHE QUE S'IL A QUELQUE CHOSE À DIRE. Un bandeau permanent sur un dossier complet
 * devient du décor, et le jour où il compte vraiment, plus personne ne le lit.
 */
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
     * `manquantsPour()` rend une liste vide tant que la règle n'est pas opposable — c'est juste
     * pour le dispatch, qui ne doit rien bloquer avant l'échéance. Mais un bandeau qui ne parle
     * qu'une fois l'échéance passée arrive trop tard : il prévient au moment où le mal est fait.
     * Ici, on regarde l'état RÉEL du dossier et on annonce la date.
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
