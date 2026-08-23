<?php

namespace App\Services\Dispatch;

use App\Events\Dispatch\MissionOfferPushed;
use App\Events\Dispatch\MissionOfferWithdrawn;
use App\Models\MissionAssignment;
use App\Models\PushNotification;
use App\Services\Push\PushService;
use Illuminate\Support\Facades\Log;

/** FAIRE ARRIVER L'OFFRE — trois canaux, parce qu'aucun ne suffit seul. 1. */
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
