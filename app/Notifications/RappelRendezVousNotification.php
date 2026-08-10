<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RappelRendezVousNotification extends Notification implements ShouldQueue
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public Booking $rdv,
        public string $timing = '24h'
    ) {}

    public function via($notifiable): array
    {
        /*
         * LE SMS EST OFFERT, LA MATRICE DÉCIDE. Le canal `sms` existait, complet, sans une seule
         * référence dans le code : aucune notification ne le proposait, donc personne n'en recevait
         * jamais — quelles que soient ses préférences. Un rappel d'intervention est justement ce
         * qu'on veut lire sans ouvrir sa boîte mail.
         */
        return $this->preferredChannels($notifiable, 'booking_reminder', ['mail', 'database', 'sms']);
    }

    /**
     * Court par nécessité : un SMS se paie au segment de 160 caractères, et le module plafonne les
     * envois par numéro. Ce qui compte tient en une ligne — quand, et où.
     */
    public function toSms(object $notifiable): string
    {
        return sprintf(
            'Brio : votre %s est prevu %s a %s. Adresse : %s',
            $this->rdv->service_display_name,
            $this->rdv->date,
            $this->rdv->heure,
            $this->rdv->location_display,
        );
    }

    public function smsIdempotencyKey(object $notifiable): string
    {
        // Deux rappels du même créneau ne doivent pas produire deux SMS : le registre déduplique
        // sur cette clé.
        return 'booking:reminder:'.$this->rdv->id.':'.$this->timing;
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Brio · Rappel de votre intervention')
            ->line("Petit rappel : votre {$this->rdv->service_display_name} est prévu dans {$this->timing}.")
            ->line('Date : '.$this->rdv->date.' à '.$this->rdv->heure)
            ->line('Adresse : '.$this->rdv->location_display)
            ->action('Voir mon espace client', route('client.dashboard'));
    }

    public function toArray($notifiable): array
    {
        return $this->basePayload([
            'type' => 'rendezvous',
            'severity' => $this->timing === '2h' ? 'warning' : 'info',
            'title' => 'Rappel de rendez-vous',
            'message' => "Rappel : votre {$this->rdv->service_display_name} est prévue dans {$this->timing}.",
            'rdv_id' => $this->rdv->id,
            'timing' => $this->timing,
            'date' => $this->rdv->date,
            'heure' => $this->rdv->heure,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'zone_name' => $this->rdv->serviceZone?->name,
            'location_display' => $this->rdv->location_display,
            'action_url' => route('client.dashboard'),
        ]);
    }
}
