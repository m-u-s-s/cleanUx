<?php

namespace App\Services\Push\Providers;

use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LE SERVICE PUSH D'EXPO — celui que les deux applications utilisent réellement.
 *
 * POURQUOI IL MANQUAIT, ET CE QUE ÇA COÛTAIT. `mobile/shared/src/push/hooks.ts` enregistre
 * l'appareil avec `provider: 'expo'` ; l'API validait `in:fcm,apns,mock` et répondait 422. AUCUN
 * appareil ne s'enregistrait, donc aucune notification ne pouvait partir — alors que six
 * notifications routaient déjà par `PushChannel`, et que tout le reste de la chaîne fonctionnait.
 * Un contrat rompu à ses deux extrémités, sur une seule ligne de validation.
 *
 * POURQUOI ACCEPTER `expo` NE SUFFISAIT PAS. `expo-notifications` rend un jeton de la forme
 * `ExponentPushToken[…]`, qui n'est ni un jeton FCM ni un jeton APNs : Expo garde la correspondance
 * de son côté et se charge du routage vers Google et Apple. Élargir la validation aurait donc
 * stocké des jetons que plus aucun fournisseur ne savait consommer — un enregistrement réussi, et
 * toujours pas une notification. Il fallait le transport, pas la permission.
 *
 * ── CE QU'EXPO RÉPOND, ET POURQUOI ON LE LIT DE PRÈS ─────────────────────────────────────────
 *
 * L'API rend TOUJOURS 200, même quand l'envoi échoue : le verdict est dans `data.status`, et le
 * motif dans `data.details.error`. Se fier au code HTTP ferait compter comme délivrées des
 * notifications qui ne sont jamais parties — exactement le défaut qu'on vient de réparer sur les
 * suppléments et le temps supplémentaire.
 *
 * `DeviceNotRegistered` signifie que l'application a été désinstallée : ce jeton ne redeviendra
 * jamais valide, et on le signale comme tel pour que `PushService` l'invalide. Les autres erreurs
 * — `MessageRateExceeded`, `MessageTooBig` — sont passagères ou de format : le jeton reste bon.
 */
class ExpoPushProvider implements PushProviderInterface
{
    /** Le point d'entrée du service, surchargeable pour les tests et les environnements fermés. */
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    public function name(): string
    {
        return 'expo';
    }

    /**
     * Les trois plateformes, parce qu'Expo route lui-même.
     *
     * Un jeton Expo ne dit pas de quelle plateforme il vient — c'est Expo qui sait, et qui envoie
     * vers APNs ou FCM selon l'appareil enregistré. Restreindre ici reviendrait à dupliquer une
     * connaissance qu'on n'a pas.
     */
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
            /*
             * `default` déclenche le son ET la vignette sur iOS ; sans lui, une notification
             * transactionnelle arrive muette et passe inaperçue. Le marketing, lui, reste discret.
             */
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

        /*
         * UN 200 N'EST PAS UN SUCCÈS. Le verdict est dans `data.status` — Expo accuse réception de
         * la requête, pas de l'envoi. Confondre les deux ferait déclarer délivrées des
         * notifications jamais parties.
         */
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

    /**
     * LA FORME DU JETON EST VÉRIFIÉE AVANT L'ENVOI.
     *
     * Un jeton FCM ou APNs posté par erreur sur ce fournisseur recevrait une erreur d'Expo au bout
     * d'un aller-retour réseau. Le refuser ici est immédiat, et le marque invalide : il n'a rien à
     * faire dans cette file.
     */
    private function ressembleAUnJetonExpo(string $jeton): bool
    {
        return str_starts_with($jeton, 'ExponentPushToken[')
            || str_starts_with($jeton, 'ExpoPushToken[');
    }

    /**
     * Le jeton d'accès n'est PAS obligatoire.
     *
     * Expo accepte les envois anonymes ; il ne devient nécessaire que si le projet active
     * « Enhanced Security for Push Notifications » dans sa console. On l'envoie s'il existe, et on
     * n'échoue pas s'il manque — sinon un projet correctement configuré sans cette option ne
     * pourrait plus rien envoyer.
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
