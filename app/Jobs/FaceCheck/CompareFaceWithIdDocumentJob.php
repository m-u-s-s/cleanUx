<?php

namespace App\Jobs\FaceCheck;

use App\Models\ProviderFaceProfile;
use App\Services\FaceCheck\FaceIdDocumentMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * L'appariement avec la pièce d'identité tourne en file : chez un fournisseur réel il prend
 * plusieurs secondes, et il n'a aucune raison de faire attendre un prestataire devant sa caméra.
 *
 * Un identifiant scalaire, pas le modèle : `SerializesModels` rechargerait une ligne qui a pu
 * changer entre-temps, et le job doit travailler sur l'état du moment où il s'exécute.
 */
class CompareFaceWithIdDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $profileId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(FaceIdDocumentMatcher $matcher): void
    {
        $profil = ProviderFaceProfile::query()->find($this->profileId);

        if ($profil === null) {
            return;
        }

        $matcher->match($profil);
    }
}
