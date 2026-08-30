<?php

namespace App\Services\Automation\Registre;

use App\Services\Automation\Contracts\Action;

/** Cle => action. Le vocabulaire des actions vit en code, jamais en base. */
class ActionRegistre
{
    /** @var array<string, Action> */
    protected array $actions = [];

    public function enregistrer(Action $action): void
    {
        $this->actions[$action->cle()] = $action;
    }

    public function trouver(string $cle): ?Action
    {
        return $this->actions[$cle] ?? null;
    }

    /** @return array<string, Action> */
    public function toutes(): array
    {
        return $this->actions;
    }
}
