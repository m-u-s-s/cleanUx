<?php

namespace App\Notifications\Gdpr;

use App\Models\GdprDataRequest;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GdprRequestCreatedNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public GdprDataRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return $this->preferredChannels($notifiable, 'gdpr_request', ['mail', 'database']);
    }

    public function toMail($notifiable): MailMessage
    {
        $type = $this->request->type;
        $ref = $this->request->reference;

        $mail = (new MailMessage)
            ->subject("CleanUx · Demande RGPD {$ref} enregistrée")
            ->greeting('Bonjour,');

        switch ($type) {
            case GdprDataRequest::TYPE_EXPORT:
                $mail->line('Votre demande d\'export de données est enregistrée.')
                    ->line('Référence : ' . $ref)
                    ->line('Vous recevrez un email dès que l\'export sera prêt.');
                break;
            case GdprDataRequest::TYPE_ERASURE:
                $mail->line('Votre demande de suppression de compte est enregistrée.')
                    ->line('Référence : ' . $ref)
                    ->line('Date d\'exécution prévue : '
                        . optional($this->request->grace_period_ends_at)->format('d/m/Y'))
                    ->line('Pour annuler cette demande, contactez le support avant cette date.');
                break;
            default:
                $mail->line('Votre demande RGPD ' . $ref . ' a bien été enregistrée.');
        }

        return $mail->action('Voir mes demandes', url('/dashboard/client/donnees'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'gdpr_request_created',
            'severity' => 'info',
            'title' => 'Demande RGPD enregistrée',
            'message' => $this->request->reference . ' — ' . $this->request->type,
            'request_id' => $this->request->id,
            'reference' => $this->request->reference,
            'request_type' => $this->request->type,
            'status' => $this->request->status,
        ]);
    }
}
