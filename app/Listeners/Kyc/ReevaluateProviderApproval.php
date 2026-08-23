<?php

namespace App\Listeners\Kyc;

use App\Events\Kyc\KycCompleted;
use App\Events\Kyc\KycRejected;
use App\Services\Onboarding\ProviderAutoApproval;
use Illuminate\Support\Facades\Log;

/** Rejoue l'évaluation du dossier quand l'identité vient d'être tranchée. */
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
