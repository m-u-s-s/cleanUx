<?php

namespace App\Jobs\Calls;

use App\Models\Call;
use App\Services\Calls\CallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * LE DÉLAI DE SONNERIE — sans lui, un appel que personne ne décroche sonne pour toujours.
 *
 * La bannière d'appel entrant ne disparaîtrait jamais, et l'historique afficherait comme « en
 * cours » des conversations qui n'ont pas eu lieu. C'est aussi ce délai qui produit l'état MANQUÉ,
 * le seul que le serveur de médias ne connaît pas : LiveKit sait qui est dans une salle à
 * l'instant T, pas qu'un appel a sonné dans le vide à 7 h du matin.
 *
 * IDEMPOTENT PAR CONSTRUCTION : `terminer()` ne touche pas un appel déjà terminé ou déjà manqué. Le
 * job peut donc être rejoué, ou arriver après que quelqu'un a raccroché, sans rien réécrire.
 */
class CloreLAppelNonRepondu implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $appelId) {}

    public function handle(CallService $appels): void
    {
        $appel = Call::find($this->appelId);

        // Décroché entre-temps : ce n'est plus un appel manqué, et il n'y a rien à faire.
        if ($appel === null || $appel->status !== Call::STATUS_RINGING) {
            return;
        }

        $appels->terminer($appel);
    }
}
