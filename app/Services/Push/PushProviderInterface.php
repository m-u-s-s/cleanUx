<?php

namespace App\Services\Push;

interface PushProviderInterface
{
    /**
     * Identifiant du provider ('mock', 'fcm', 'apns').
     */
    public function name(): string;

    /**
     * Plateformes supportées par ce provider (ex: ['ios', 'android', 'web']).
     *
     * @return array<int,string>
     */
    public function supportsPlatforms(): array;

    /**
     * Envoie une notification push à un device token.
     */
    public function send(PushSendRequest $request): PushSendResult;
}
