<?php

namespace App\Realtime\Contracts;

/** Marqueur que les Events broadcast peuvent implémenter pour activer la traçabilité via RealtimeBroadcastService. */
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
