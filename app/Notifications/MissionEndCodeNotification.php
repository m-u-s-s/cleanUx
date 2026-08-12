<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * PORTE AU CLIENT LE CODE DE FIN, QUI N'AVAIT AUCUN MOYEN DE L'ATTEINDRE.
 *
 * Le code en clair n'est jamais stocké — `MissionVerificationCodeService` n'en garde que
 * l'empreinte — donc aucune page ne peut le relire en base. Il n'existe qu'à l'instant de sa
 * génération, et doit être confié à un porteur à ce moment précis ou il est perdu.
 *
 * Le suivi web du client lisait ces six chiffres dans `session('mission_end_code_…')`, une clé
 * écrite dans la session DU PRESTATAIRE : jamais présente chez le client, et carrément inexistante
 * quand le prestataire agit depuis l'application mobile, authentifiée par jeton et sans session.
 * L'encadré « Code de fin disponible » affichait donc en permanence son texte de repli.
 *
 * Le code de DÉBUT ne souffrait pas de cela : il voyage déjà dans la charge de
 * [[EmployeArriveNotification]], que le client peut lire. Cette classe donne au code de fin le
 * même porteur — symétrie volontaire, plutôt qu'un second mécanisme à maintenir.
 *
 * PAS DE CANAL SMS ICI, et c'est délibéré : le SMS du code de fin est déjà émis séparément par
 * `MissionLifecycleService`. En ajouter un second doublerait la consommation du plafond de cinq
 * messages par heure et par numéro — précisément ce qui, sur la base de démonstration, faisait
 * basculer les envois en `rate_limited` et privait le client de tout code.
 */
class MissionEndCodeNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public Mission $mission,
        public string $endCode
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_end_code',
            ['database', 'mail'],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de fin de mission')
            ->greeting('Bonjour,')
            ->line('Voici le code de fin pour la mission '.$this->mission->booking?->booking_reference.'.')
            ->line('Code de fin de mission : '.$this->endCode)
            ->line('Communiquez-le au prestataire en fin de service pour clôturer l’intervention.')
            ->action('Voir le suivi', url('/dashboard/client/rendezvous'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_end_code',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'service_label' => $this->mission->booking?->service_display_name,
            'status' => $this->mission->status,
            'end_code' => $this->endCode,
        ];
    }
}
