<?php

namespace App\Services\Push\Providers;

use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** LE SERVICE PUSH D'EXPO — celui que les deux applications utilisent réellement. */
class ExpoPushProvider implements PushProviderInterface
{
    /** Le point d'entrée du service, surchargeable pour les tests et les environnements fermés. */
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function name(): string
    {
        return 'expo';
    }

    /** Les trois plateformes, parce qu'Expo route lui-même. */
    public function supportsPlatforms(): array
    {
        return ['ios', 'android', 'web'];
    }

    public function send(PushSendRequest $request): PushSendResult
    {
        if (! $this->ressembleAUnJetonExpo($request->token)) {
            return PushSendResult::failed(
                'Jeton non reconnu par Expo : '.mb_substr($request->token, 0, 24),
                'expo_token_format',
                tokenInvalid: true,
            );
        }

        $message = [
            'to' => $request->token,
            'title' => $request->title,
            'body' => $request->body,
            'data' => $request->data,
            // `default` déclenche le son ET la vignette sur iOS ; sans lui, une notification transactionnelle arrive muette et passe inaperçue.
            'sound' => $request->category === 'marketing' ? null : 'default',
            'priority' => $request->category === 'marketing' ? 'normal' : 'high',
            'channelId' => $request->category,
        ];

        try {
            $reponse = Http::acceptJson()
                ->timeout((int) Config::get('push.providers.expo.http_timeout', 10))
                ->withHeaders($this->entetes())
                ->post($this->endpoint(), $message);
        } catch (\Throwable $e) {
            Log::warning('[push] Expo injoignable', ['error' => $e->getMessage()]);

            return PushSendResult::failed('Expo injoignable : '.$e->getMessage(), 'expo_transport');
        }

        if (! $reponse->successful()) {
            return PushSendResult::failed(
                'Expo a répondu '.$reponse->status(),
                'expo_http_'.$reponse->status(),
                raw: (array) $reponse->json(),
            );
        }

        $corps = (array) $reponse->json();
        $ticket = (array) data_get($corps, 'data', []);

        // UN 200 N'EST PAS UN SUCCÈS.
        if ((string) ($ticket['status'] ?? '') !== 'ok') {
            $motif = (string) data_get($ticket, 'details.error', $ticket['message'] ?? 'erreur inconnue');

            return PushSendResult::failed(
                (string) ($ticket['message'] ?? $motif),
                'expo_'.$motif,
                // Seule cette erreur-là est définitive : l'application a été désinstallée.
                tokenInvalid: $motif === 'DeviceNotRegistered',
                raw: $corps,
            );
        }

        return PushSendResult::accepted(
            (string) ($ticket['id'] ?? ''),
            'sent',
            $corps,
        );
    }

    /** LA FORME DU JETON EST VÉRIFIÉE AVANT L'ENVOI. */
    private function ressembleAUnJetonExpo(string $jeton): bool
    {
        return str_starts_with($jeton, 'ExponentPushToken[')
            || str_starts_with($jeton, 'ExpoPushToken[');
    }

    /**
     * Le jeton d'accès n'est PAS obligatoire.
     *
     * @return array<string, string>
     */
    private function entetes(): array
    {
        $jeton = (string) Config::get('push.providers.expo.access_token', '');

        return $jeton === '' ? [] : ['Authorization' => 'Bearer '.$jeton];
    }

    private function endpoint(): string
    {
        return (string) Config::get('push.providers.expo.endpoint', self::ENDPOINT);
    }
}
