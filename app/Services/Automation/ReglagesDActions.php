<?php

namespace App\Services\Automation;

use App\Models\AutomationActionSetting;
use App\Models\User;
use App\Services\Automation\Registre\ActionRegistre;
use App\Support\ActivityLogger;

/** La porte de l'autonomie : absence de ligne = a valider, jamais l'inverse. */
class ReglagesDActions
{
    public function __construct(protected ActionRegistre $actions) {}

    public function estAutonome(string $actionCle): bool
    {
        if ($this->actions->trouver($actionCle) === null) {
            return false;
        }

        return (bool) AutomationActionSetting::query()
            ->where('action_cle', $actionCle)
            ->value('autonome');
    }

    public function basculer(string $actionCle, bool $autonome, User $par): void
    {
        $reglage = AutomationActionSetting::updateOrCreate(
            ['action_cle' => $actionCle],
            ['autonome' => $autonome, 'modifie_par' => $par->id, 'modifie_le' => now()]
        );

        ActivityLogger::log('automation.reglage_'.($autonome ? 'autonome' : 'a_valider'), $reglage, [
            'action_cle' => $actionCle,
        ]);
    }

    /** @return array<string, bool> seulement les cles connues du registre, jamais un reglage orphelin */
    public function tous(): array
    {
        $reglages = AutomationActionSetting::query()->pluck('autonome', 'action_cle');

        $resultat = [];
        foreach ($this->actions->toutes() as $cle => $action) {
            $resultat[$cle] = (bool) ($reglages[$cle] ?? false);
        }

        return $resultat;
    }
}
