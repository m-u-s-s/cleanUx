<?php

namespace App\Services\Calls;

use App\Models\Call;
use App\Models\Channel;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * OUVRIR UNE SALLE LIVEKIT, ET DÉLIVRER LE JETON QUI Y DONNE ACCÈS.
 *
 * REMPLACE `VideoCallService`, un squelette qui levait sur chaque méthode depuis son écriture et
 * qui a depuis été supprimé du dépôt.
 * `MaskedCallService` (Twilio Proxy) reste INTACT : il répond à un besoin différent — masquer les
 * numéros entre client et prestataire — et le confondre avec celui-ci donnerait aux deux le pire
 * des deux.
 *
 * LE JETON EST SIGNÉ ICI, SANS SDK. Un jeton LiveKit est un JWT HS256 dont la charge utile décrit la
 * salle et les droits ; le produire tient en vingt lignes. Ajouter une dépendance pour cela
 * imposerait une mise à jour de `composer.lock` et une surface de sécurité de plus, pour un format
 * que l'on maîtrise entièrement.
 *
 * LE SECRET NE SORT JAMAIS D'ICI. Le client reçoit un jeton portant SES droits sur UNE salle et une
 * expiration courte — jamais la clé d'API, qui permettrait d'entrer dans n'importe quelle salle de
 * n'importe quelle société.
 */
class CallService
{
    /**
     * Les appels sont-ils utilisables ?
     *
     * SANS CLÉ, ON NE PROPOSE RIEN. Un jeton signé avec un secret vide serait rejeté par le serveur
     * LiveKit, et l'application afficherait un bouton qui échoue — ce qui est pire que l'absence du
     * bouton.
     */
    public function estConfigure(): bool
    {
        return filled(config('livekit.url'))
            && filled(config('livekit.api_key'))
            && filled(config('livekit.api_secret'));
    }

    /**
     * Ouvrir un appel dans un canal.
     *
     * Le nom de la salle porte un identifiant ALÉATOIRE, pas celui du canal : deux appels successifs
     * dans le même fil ne doivent pas partager la salle, sinon un participant en retard rejoindrait
     * la conversation précédente.
     */
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

    /**
     * Le jeton d'accès de cette personne à cette salle.
     *
     * `identity` est l'identifiant utilisateur : c'est ce que les autres participants voient, et ce
     * qui permet à LiveKit de refuser deux connexions simultanées du même compte.
     */
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
                /*
                 * PAS DE `roomCreate`, PAS DE `roomAdmin`. Le participant rejoint une salle que le
                 * serveur a nommée ; lui donner le droit d'en créer laisserait n'importe quel
                 * client ouvrir des salles hors de toute trace côté produit.
                 */
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

    /**
     * Terminer.
     *
     * UN APPEL QUI SONNAIT ENCORE DEVIENT « MANQUÉ », PAS « TERMINÉ ». La distinction est tout ce
     * qui compte quand on reprend son téléphone : « terminé » se lit comme une conversation qui a
     * eu lieu.
     */
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
     * `base64url` — et non `base64` — parce qu'un JWT voyage dans une URL et un en-tête : `+`, `/`
     * et `=` y seraient réinterprétés, et la signature ne correspondrait plus.
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
