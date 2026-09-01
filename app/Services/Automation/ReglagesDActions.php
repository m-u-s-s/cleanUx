<?php

namespace App\Services\Automation;

use App\Models\AutomationActionSetting;
use App\Models\User;
use App\Services\Automation\Contracts\Action;
use App\Services\Automation\Registre\ActionRegistre;
use App\Support\ActivityLogger;

/** La porte de l'autonomie : absence de ligne = a valider, jamais l'inverse. */
class ReglagesDActions
{
    public function __construct(protected ActionRegistre $actions) {}

    public function estAutonome(string $actionCle): bool
    {
        $action = $this->actions->trouver($actionCle);

        if ($action === null) {
            return false;
        }

        $reglage = AutomationActionSetting::query()->where('action_cle', $actionCle)->first();

        return $reglage !== null && $this->tientToujours($reglage, $action);
    }

    public function basculer(string $actionCle, bool $autonome, User $par): void
    {
        $reglage = AutomationActionSetting::updateOrCreate(
            ['action_cle' => $actionCle],
            [
                'autonome' => $autonome,
                // FIGE CE QUE L'HUMAIN A CONFIRME : sans ca, une action qui se met a toucher au
                // domaine heriterait d'une autonomie que personne n'a accordee pour cette nature.
                'domaine_au_moment_du_reglage' => (bool) $this->actions->trouver($actionCle)?->toucheAuDomaine(),
                'modifie_par' => $par->id,
                'modifie_le' => now(),
            ]
        );

        ActivityLogger::log('automation.reglage_'.($autonome ? 'autonome' : 'a_valider'), $reglage, [
            'action_cle' => $actionCle,
        ]);
    }

    /** @return array<string, bool> seulement les cles connues du registre, jamais un reglage orphelin */
    public function tous(): array
    {
        $reglages = AutomationActionSetting::query()->get()->keyBy('action_cle');

        $resultat = [];
        foreach ($this->actions->toutes() as $cle => $action) {
            $reglage = $reglages->get($cle);
            $resultat[$cle] = $reglage !== null && $this->tientToujours($reglage, $action);
        }

        return $resultat;
    }

    /** L'autonomie ne vaut que pour la nature confirmee : si l'action a change depuis, la
     *  confirmation renforcee se redemande — c'est le contrepoids 3, en invariant. */
    protected function tientToujours(AutomationActionSetting $reglage, Action $action): bool
    {
        return $reglage->autonome && $reglage->domaine_au_moment_du_reglage === $action->toucheAuDomaine();
    }
}
