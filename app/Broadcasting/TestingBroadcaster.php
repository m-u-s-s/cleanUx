<?php

namespace App\Broadcasting;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;

/**
 * Broadcaster utilisé en environnement de tests.
 *
 * Honore les callbacks de routes/channels.php pour les tests d'auth
 * Broadcasting (private + presence) sans dépendre d'une connexion Pusher
 * réelle, et fait du `broadcast()` un no-op pour éviter les appels HTTP
 * sortants lors des tests qui dispatch des events.
 */
class TestingBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name)
            || ($this->isGuardedChannel($request->channel_name)
                && ! $this->retrieveUser($request, $channelName))
        ) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    public function validAuthenticationResponse($request, $result)
    {
        // Pour les canaux presence, renvoyer une payload JSON minimale.
        if (str_starts_with($request->channel_name, 'presence-')) {
            $user = $this->retrieveUser($request, $this->normalizeChannelName($request->channel_name));

            return response()->json([
                'channel_data' => [
                    'user_id'   => $user?->getAuthIdentifier(),
                    'user_info' => is_array($result) ? $result : [],
                ],
            ]);
        }

        // Pour private channels : un payload simple suffit pour les tests.
        return response()->json([
            'auth' => 'testing:' . $request->socket_id,
        ]);
    }

    public function broadcast(array $channels, $event, array $payload = [])
    {
        // No-op : on ne diffuse rien en test.
    }
}
