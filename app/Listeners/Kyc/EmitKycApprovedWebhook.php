<?php

namespace App\Listeners\Kyc;

use App\Events\Kyc\KycCompleted;
use App\Support\Webhooks\BusinessEventEmitter;

class EmitKycApprovedWebhook
{
    public function handle(KycCompleted $event): void
    {
        $v = $event->verification;
        $decision = (string) ($v->decision ?? '');
        if ($decision !== 'approved') {
            return;
        }
        BusinessEventEmitter::emit(
            eventCode: 'provider.kyc_approved',
            payload: [
                'verification_id' => $v->id,
                'user_id' => $v->user_id ?? null,
                'provider' => $v->provider ?? null,
                'status' => $v->status ?? null,
                'decision' => $decision,
                'completed_at' => optional($v->completed_at ?? null)?->toIso8601String(),
            ],
            idempotencyKey: 'provider.kyc_approved:' . $v->id,
            sourceType: get_class($v),
            sourceId: (int) $v->id,
        );
    }
}
