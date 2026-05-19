<?php

namespace App\Realtime\Contracts;

/**
 * Marqueur que les Events broadcast peuvent implémenter pour activer
 * la traçabilité via RealtimeBroadcastService.
 *
 * - broadcastCategory() : libellé métier (mission_eta, position, chat, ...)
 * - broadcastIdempotencyKey() : prévient le double-broadcast d'un même event
 *   (typiquement: <category>:<source-id>:<seq>)
 * - broadcastSourceType() / broadcastSourceId() : pour pouvoir filtrer les
 *   events liés à un Mission / Booking / Conversation dans l'admin.
 */
interface TracksBroadcastLedger
{
    public function broadcastCategory(): string;

    public function broadcastIdempotencyKey(): ?string;

    public function broadcastSourceType(): ?string;

    public function broadcastSourceId(): ?int;

    /**
     * Payload sérialisable pour le ledger.
     *
     * @return array<string,mixed>
     */
    public function broadcastLedgerPayload(): array;
}
