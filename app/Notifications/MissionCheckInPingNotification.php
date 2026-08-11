<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * « Tout se passe bien ? » — au milieu de l'intervention, pas après.
 *
 * C'est le moment qui fait toute la valeur de ce message. Posée à la fin, la question ne sert plus
 * à rien : il ne reste que l'avis à écrire et le litige à ouvrir, et les deux coûtent bien plus cher
 * à tout le monde qu'un mot dit au prestataire pendant qu'il est encore là.
 *
 * DISCRET PAR CONSTRUCTION. Pas de SMS : ce message n'attend pas de réponse urgente et ne doit pas
 * consommer le plafond d'envois du client — celui-ci doit rester disponible pour un code de présence
 * ou un imprévu. Et pas de courriel non plus si la personne est joignable en push : recevoir un mail
 * « tout va bien ? » pendant qu'un prestataire travaille chez soi inquiète plus qu'il ne rassure.
 */
class MissionCheckInPingNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(public Mission $mission) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_started',
            ['database', WebPushChannel::class],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Brio · Tout se passe bien ?')
            ->line('Votre intervention est en cours. Un souci ? Dites-le maintenant, pendant qu’il est encore temps.')
            ->action('Répondre en un geste', url('/dashboard/client'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_checkin_ping',
            'mission_id' => $this->mission->id,
            'booking_id' => $this->mission->booking_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Tout se passe bien ?',
            // Deux mots, deux réponses : une question posée pendant qu'on fait autre chose doit
            // pouvoir se traiter sans lire.
            'body' => 'Répondez en un geste.',
            'data' => ['mission_id' => $this->mission->id, 'action' => 'checkin_ping'],
        ];
    }
}
