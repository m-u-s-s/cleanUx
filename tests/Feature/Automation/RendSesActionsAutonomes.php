<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Services\Automation\ReglagesDActions;

/** Une action n'execute que si un administrateur l'a rendue autonome — par le chemin reel. */
trait RendSesActionsAutonomes
{
    private function rendreAutonome(string ...$cles): void
    {
        // Un client, pas un administrateur : les tests qui comptent sur zero admin actif
        // (`notifier.admins` echoue alors) doivent rester justes.
        $auteur = User::factory()->create();

        foreach ($cles as $cle) {
            app(ReglagesDActions::class)->basculer($cle, true, $auteur);
        }
    }
}
