<?php

namespace App\Listeners\Kyc;

use App\Events\Kyc\KycCompleted;
use App\Events\Kyc\KycRejected;
use App\Services\Onboarding\ProviderAutoApproval;
use Illuminate\Support\Facades\Log;

/**
 * Rejoue l'évaluation du dossier quand l'identité vient d'être tranchée.
 *
 * La décision d'identité est le verdict le plus structurant, et il tombe hors de l'application —
 * par webhook, ou par une synchronisation demandée depuis l'écran. C'est donc ici, et non dans
 * un parcours utilisateur, qu'un dossier devient activable.
 *
 * Soft-fail : une inscription valide ne doit pas être compromise parce que l'orchestration
 * d'activation a échoué. Le dossier reste alors en attente et un administrateur le traite,
 * exactement comme avant l'existence de ce service.
 */
class ReevaluateProviderApproval
{
    public function __construct(protected ProviderAutoApproval $autoApproval) {}

    public function handle(KycCompleted|KycRejected $event): void
    {
        $user = $event->verification->user;

        if (! $user) {
            return;
        }

        try {
            if ($event instanceof KycRejected) {
                if ($profile = $user->providerProfile) {
                    $this->autoApproval->flagForManualReview(
                        $profile,
                        "Vérification d'identité refusée par le contrôle automatique",
                    );
                }

                return;
            }

            $this->autoApproval->evaluate($user);
        } catch (\Throwable $e) {
            Log::warning('[provider_auto_approval] réévaluation impossible (non bloquant)', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
