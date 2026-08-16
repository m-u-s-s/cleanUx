<?php

namespace App\Notifications\FaceCheck;

use App\Models\ProviderFaceProfile;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * « VOUS ÊTES SUSPENDU, ET VOICI POURQUOI. »
 *
 * Sans cette notification, un prestataire découvrait sa suspension en essayant de passer en ligne
 * un lundi matin, devant un client qui l'attend. Il faut le lui dire au moment où c'est décidé,
 * lui donner le motif, et surtout lui dire CE QUI NE MARCHERA PAS : attendre, et cliquer sur
 * « ça ne marche pas ». Un blocage qu'on ne sait pas comment lever se transforme en appel au
 * support, puis en désinstallation.
 *
 * Pas `ShouldQueue`, comme les alertes de sécurité : une file bouchée est exactement le moment où
 * ce message compte.
 */
class FaceCheckBlockedNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;

    public function __construct(public string $raison) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        /*
         * Catégorie `transactional` par défaut de résolution : c'est une conséquence directe de
         * l'usage du service, pas une communication qu'on peut couper. `preferredChannels` garde
         * de toute façon `database` même si tout est désactivé — un blocage muet serait le pire
         * des deux mondes.
         */
        return $this->preferredChannels($notifiable, 'face_check_blocked', ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('face_check.notifications.blocked.subject'))
            ->greeting(__('face_check.notifications.blocked.greeting', ['name' => $notifiable->name ?? '']))
            ->line(__('face_check.notifications.blocked.line_reason', ['reason' => $this->libelleDuMotif()]))
            ->line(__('face_check.notifications.blocked.line_action'))
            ->line(__('face_check.notifications.blocked.line_report'))
            ->action(__('face_check.notifications.blocked.action'), url('/provider/verification-faciale'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->basePayload([
            'type' => 'face_check_blocked',
            'block_reason' => $this->raison,
            'message' => __('face_check.notifications.blocked.line_reason', ['reason' => $this->libelleDuMotif()]),
            'url' => '/provider/verification-faciale',
        ]);
    }

    private function libelleDuMotif(): string
    {
        $cle = match ($this->raison) {
            ProviderFaceProfile::BLOCK_FAILED_CHECKS => 'failed_checks',
            ProviderFaceProfile::BLOCK_ID_MISMATCH => 'id_mismatch',
            ProviderFaceProfile::BLOCK_CONSENT_WITHDRAWN => 'consent_withdrawn',
            ProviderFaceProfile::BLOCK_ADMIN => 'admin_decision',
            default => 'unknown',
        };

        return __("face_check.notifications.blocked.reason.{$cle}");
    }
}
