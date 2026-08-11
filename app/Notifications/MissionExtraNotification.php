<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Models\MissionExtra;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * « Votre prestataire propose un supplément, et il attend votre réponse maintenant. »
 *
 * LA FENÊTRE EST COURTE, et c'est toute la difficulté de ce message. Le prestataire est chez le
 * client, à l'instant : une réponse qui arrive après son départ ne sert plus à rien, et l'occasion
 * de faire le travail est perdue pour les deux. Le SMS figure donc dans la liste par défaut, comme
 * pour l'arrivée — le client visé est précisément celui qui n'est pas devant son écran.
 *
 * LE PRIX EST DANS LE MESSAGE. Un « votre prestataire propose un supplément » sans montant oblige à
 * ouvrir l'application pour savoir s'il faut s'en occuper : c'est le geste de trop quand on veut une
 * réponse en un tap. Le chiffre permet de décider avant même d'ouvrir.
 */
class MissionExtraNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public Mission $mission,
        public MissionExtra $extra,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_extra',
            ['database', 'mail', 'sms', WebPushChannel::class],
        );
    }

    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Brio : votre prestataire propose %s pour %s EUR. Repondez depuis le suivi de mission.',
            $this->extra->label,
            number_format($this->extra->montantEnEuros(), 2, '.', ''),
        );
    }

    public function smsIdempotencyKey(object $notifiable): string
    {
        // Une proposition rejouée par la file ne doit pas coûter un second SMS au plafond du client.
        return 'mission:extra:'.$this->extra->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Brio · Un supplément vous est proposé')
            ->greeting('Bonjour,')
            ->line('Votre prestataire, sur place, propose une prestation supplémentaire :')
            ->line($this->extra->label.' — '.number_format($this->extra->montantEnEuros(), 2, ',', ' ').' €')
            ->when(
                filled($this->extra->description),
                fn (MailMessage $message) => $message->line($this->extra->description),
            )
            // Le devis d'origine ne bouge pas : c'est la première chose à dire, parce que c'est la
            // première inquiétude.
            ->line('Votre devis initial reste inchangé : ce supplément est une ligne séparée.')
            ->action('Répondre', url('/dashboard/client'))
            ->line('Sans réponse de votre part, la prestation supplémentaire ne sera pas effectuée.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_extra_proposed',
            'mission_id' => $this->mission->id,
            'extra_id' => $this->extra->id,
            'label' => $this->extra->label,
            'price_cents' => $this->extra->price_cents,
            'currency' => $this->extra->currency,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Supplément proposé',
            'body' => $this->extra->label.' — '.number_format($this->extra->montantEnEuros(), 2, ',', ' ').' €',
            'data' => ['mission_id' => $this->mission->id, 'extra_id' => $this->extra->id],
        ];
    }
}
