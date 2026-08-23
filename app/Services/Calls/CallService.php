<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Str;

/** OUVRIR UNE SALLE LIVEKIT, ET DÉLIVRER LE JETON QUI Y DONNE ACCÈS. */
class CallService
{
    /** Les appels sont-ils utilisables ? SANS CLÉ, ON NE PROPOSE RIEN. */
    public function estConfigure(): bool
    {
        return filled(config('livekit.url'))
            && filled(config('livekit.api_key'))
            && filled(config('livekit.api_secret'));
    }

    /** Ouvrir un appel dans un canal. */
    public function ouvrir(Channel $canal, User $initiateur, string $type = 'audio'): Call
    {
        return Call::create([
            'channel_id' => $canal->id,
            'initiator_user_id' => $initiateur->id,
            'type' => $type,
            'status' => Call::STATUS_RINGING,
            'room_name' => 'cx-'.$canal->id.'-'.Str::lower(Str::random(12)),
            'started_at' => now(),
        ]);
    }

    /** Le jeton d'accès de cette personne à cette salle. */
    public function jetonPour(Call $appel, User $utilisateur): string
    {
        $maintenant = time();
        $ttl = (int) config('livekit.token_ttl', 7200);

        return $this->signer([
            'iss' => (string) config('livekit.api_key'),
            'sub' => (string) $utilisateur->id,
            'iat' => $maintenant,
            // Court par nature : un jeton d'accès à une salle n'a pas à survivre à l'appel.
            'exp' => $maintenant + $ttl,
            'nbf' => $maintenant,
            'name' => $utilisateur->name,
            'video' => [
                'room' => $appel->room_name,
                'roomJoin' => true,
                'canPublish' => true,
                'canSubscribe' => true,
                // PAS DE `roomCreate`, PAS DE `roomAdmin`.
                'canPublishData' => true,
            ],
        ]);
    }

    /** Marquer l'appel comme décroché — c'est ce qui arrête la sonnerie côté appelant. */
    public function repondre(Call $appel): void
    {
        if ($appel->status !== Call::STATUS_RINGING) {
            return;
        }

        $appel->update(['status' => Call::STATUS_ACTIVE, 'answered_at' => now()]);
    }

    /** Terminer. UN APPEL QUI SONNAIT ENCORE DEVIENT « MANQUÉ », PAS « TERMINÉ ». */
    public function terminer(Call $appel): void
    {
        if (in_array($appel->status, [Call::STATUS_ENDED, Call::STATUS_MISSED], true)) {
            return;
        }

        $appel->update([
            'status' => $appel->status === Call::STATUS_RINGING ? Call::STATUS_MISSED : Call::STATUS_ENDED,
            'ended_at' => now(),
        ]);
    }

    /**
     * Signer un JWT HS256.
     *
     * @param  array<string, mixed>  $charge
     */
    private function signer(array $charge): string
    {
        $entete = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $corps = $this->base64url(json_encode($charge, JSON_THROW_ON_ERROR));

        $signature = hash_hmac(
            'sha256',
            $entete.'.'.$corps,
            (string) config('livekit.api_secret'),
            true,
        );

        return $entete.'.'.$corps.'.'.$this->base64url($signature);
    }

    private function base64url(string $valeur): string
    {
        return rtrim(strtr(base64_encode($valeur), '+/', '-_'), '=');
    }
}
