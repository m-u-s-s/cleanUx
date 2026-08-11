<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Models\MissionReport;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * « Votre intervention est terminée, voici ce qui a été fait. »
 *
 * LE RAPPORT ÉTAIT PRODUIT ET RANGÉ SUR UN DISQUE PRIVÉ que le client ne peut pas atteindre.
 * Un compte rendu que le destinataire ne reçoit pas est un fichier, pas un compte rendu — et c'est
 * précisément la pièce qu'on cherche trois semaines plus tard, quand une contestation arrive et que
 * plus personne ne se souvient de l'état du lieu.
 *
 * PAS DE SMS ICI, contrairement à l'arrivée ou aux suppléments. Ce message n'attend rien du client
 * et ne périme pas : il se lit le soir, tranquillement. Occuper son plafond d'envois pour cela
 * priverait la plateforme d'un SMS le jour où quelque chose d'urgent devra passer.
 */
class MissionReportReadyNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public Mission $mission,
        public MissionReport $report,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_completed',
            ['database', 'mail', WebPushChannel::class],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Brio · Votre rapport d’intervention')
            ->greeting('Bonjour,')
            ->line('Votre intervention est terminée. Voici ce qui a été fait :')
            ->line('Tâches réalisées : '.$this->report->checklist_completion_rate.' %')
            ->line('Photos avant : '.$this->report->before_photos_count.
                ' — après : '.$this->report->after_photos_count);

        if ($this->report->incident_count > 0) {
            // L'imprévu se dit AVANT le lien : c'est la seule ligne qui peut appeler une réaction.
            $message->line('Imprévus signalés pendant l’intervention : '.$this->report->incident_count);
        }

        return $message
            ->action('Voir le détail', url('/dashboard/client'))
            ->line('Ce rapport reste disponible depuis votre espace, avec les photos.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_report_ready',
            'mission_id' => $this->mission->id,
            'report_id' => $this->report->id,
            'report_number' => $this->report->report_number,
            'incident_count' => $this->report->incident_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Intervention terminée',
            'body' => 'Votre rapport est disponible.',
            'data' => ['mission_id' => $this->mission->id],
        ];
    }
}
