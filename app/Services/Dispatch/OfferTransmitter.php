<?php

namespace App\Services\Dispatch;

use App\Events\Dispatch\MissionOfferPushed;
use App\Events\Dispatch\MissionOfferWithdrawn;
use App\Models\MissionAssignment;
use App\Models\PushNotification;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Log;

/**
 * FAIRE ARRIVER L'OFFRE — trois canaux, parce qu'aucun ne suffit seul.
 *
 * 1. TEMPS RÉEL sur `user.{id}` : le seul assez rapide pour un compte à rebours de vingt secondes,
 *    et le seul qui ouvre la modale sans que le prestataire touche son téléphone. Il ne marche que
 *    si l'application est au premier plan et la socket vivante.
 * 2. PUSH : réveille l'application en arrière-plan. Il traverse les portails et les métros, mais
 *    arrive quand il arrive — jamais garanti sous vingt secondes.
 * 3. POLLING sur l'inbox : la SOURCE DE VÉRITÉ. Quand la socket et le push manquent tous les deux,
 *    l'offre est quand même dans la liste — c'est ce qui empêche un défaut d'infrastructure de
 *    rendre la plateforme muette.
 *
 * AUCUN ÉCHEC N'ARRÊTE LE DISPATCH. Un jeton mort ou un module de diffusion éteint doit priver ce
 * prestataire de son alerte, pas la recherche de sa suite : `config/broadcasting.php` a `null` pour
 * défaut sur ce dépôt, et une exception ici ferait tomber toute la chaîne d'offres en local.
 */
class OfferTransmitter
{
    public function __construct(
        protected PushService $push,
        protected OfferPayloadBuilder $payloads,
    ) {}

    /**
     * L'offre part sur les trois canaux. Rend la charge utile réellement transmise.
     *
     * @return array<string, mixed>
     */
    public function transmit(MissionAssignment $assignment, ?int $distanceM = null): array
    {
        $payload = $this->payloads->build($assignment, $distanceM);

        try {
            MissionOfferPushed::dispatch((int) $assignment->user_id, $payload);
        } catch (\Throwable $e) {
            Log::warning('OfferTransmitter: diffusion temps réel impossible', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $provider = $assignment->user;

            if ($provider) {
                $this->push->dispatchToUser(
                    $provider,
                    'Nouvelle mission',
                    $this->body($payload),
                    ['type' => 'mission_offer'] + $payload,
                    PushNotification::CATEGORY_TRANSACTIONAL,
                    // Idempotent : un rejeu de la file ne fait pas vibrer deux fois le téléphone.
                    'mission_offer:'.$assignment->id,
                    $assignment,
                );
            }
        } catch (\Throwable $e) {
            Log::warning('OfferTransmitter: envoi push impossible', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payload;
    }

    /** L'offre n'est plus à prendre : la modale se ferme d'elle-même, chez tout le monde. */
    public function withdraw(MissionAssignment $assignment, string $reason = 'taken'): void
    {
        try {
            MissionOfferWithdrawn::dispatch((int) $assignment->user_id, (int) $assignment->id, $reason);
        } catch (\Throwable $e) {
            Log::warning('OfferTransmitter: retrait temps réel impossible', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @param  array<string, mixed>  $payload */
    protected function body(array $payload): string
    {
        $trade = $payload['trade_name'] ?? 'Mission';
        $distance = $payload['distance_km'] ?? null;

        return $distance === null
            ? sprintf('%s — répondez vite.', $trade)
            : sprintf('%s à %s km de vous — répondez vite.', $trade, number_format((float) $distance, 1, ',', ' '));
    }
}
