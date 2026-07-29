<?php

namespace App\Jobs\Kyb;

use App\Models\BusinessEntity;
use App\Services\KybV2\BusinessOnboardingService;
use App\Services\Onboarding\ProviderAutoApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie une entreprise auprès des registres officiels, hors du chemin d'inscription.
 *
 * Les contrôles sortants — INSEE ou registre national pour l'immatriculation, VIES pour la TVA,
 * criblage des sanctions — prennent chacun une à deux secondes. Les exécuter pendant l'inscription
 * la ralentirait d'autant, et une indisponibilité de l'un d'eux ferait échouer une création de
 * compte par ailleurs valide. Ils sont donc mis en file.
 *
 * Le verdict peut ouvrir le compte : la réévaluation est rejouée à la fin, comme après une
 * décision d'identité.
 */
class VerifyBusinessEntity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public BusinessEntity $entity) {}

    public function handle(
        BusinessOnboardingService $onboarding,
        ProviderAutoApproval $autoApproval,
    ): void {
        $onboarding->runVerifications($this->entity);
        $onboarding->runSanctionsScreening($this->entity);
        $onboarding->refreshRiskAndStatus($this->entity);

        $owner = $this->entity->fresh()?->owner;

        if (! $owner) {
            return;
        }

        try {
            $autoApproval->evaluate($owner);
        } catch (\Throwable $e) {
            // Le verdict d'entreprise est acquis et persisté ; seule la réévaluation a échoué.
            // Le dossier reste alors en attente d'un administrateur, comme avant ce service.
            Log::warning('[kyb] réévaluation impossible après vérification (non bloquant)', [
                'entity_id' => $this->entity->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
