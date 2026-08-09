<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * L'offre qui fait apparaître la modale, poussée sur le canal personnel du prestataire.
 *
 * LE CANAL EST CELUI DE LA PERSONNE, pas celui de la mission : au moment où l'offre part, le
 * prestataire n'est abonné à rien qui la concerne — il ne saura qu'elle existe qu'en la recevant.
 * `user.{id}` est déjà autorisé dans `routes/channels.php` et l'application y est branchée.
 *
 * LE COMPTE À REBOURS VOYAGE EN `expires_at` SERVEUR, jamais en « il vous reste 20 secondes ». Un
 * nombre de secondes calculé à l'émission dérive de tout ce que le réseau met à livrer le message,
 * et la modale afficherait alors un temps que le serveur ne respecte pas — le prestataire verrait
 * « 6 s » sur une offre déjà escaladée.
 *
 * LA CHARGE UTILE EST COMPLÈTE. Une notification qui ne dirait que « vous avez une offre » forcerait
 * un aller-retour HTTP avant d'afficher quoi que ce soit : deux secondes prises sur les vingt.
 */
class MissionOfferPushed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $offer
     */
    public function __construct(
        public int $providerUserId,
        public array $offer,
    ) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->providerUserId)];
    }

    public function broadcastAs(): string
    {
        return 'MissionOfferPushed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['offer' => $this->offer];
    }
}
