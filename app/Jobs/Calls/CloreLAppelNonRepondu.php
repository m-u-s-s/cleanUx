<?php

namespace App\Jobs\Calls;

use App\Models\Call;
use App\Services\Calls\CallService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** LE DÉLAI DE SONNERIE — sans lui, un appel que personne ne décroche sonne pour toujours. */
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
