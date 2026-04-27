<?php

namespace App\Notifications;

use App\Models\RendezVous;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RendezVousReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public RendezVous $rendezVous,
        public string $type = '24h',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $title = $this->type === '2h'
            ? 'Votre rendez-vous approche'
            : 'Rappel de votre rendez-vous';

        return (new MailMessage)
            ->subject($title)
            ->greeting('Bonjour '.$notifiable->name)
            ->line('Votre intervention est prévue le '.$this->rendezVous->date?->format('d/m/Y').' à '.substr((string) $this->rendezVous->heure, 0, 5).'.')
            ->line('Service : '.$this->rendezVous->service_display_name)
            ->action('Voir mon rendez-vous', route('client.rendezvous.index'))
            ->line('Merci pour votre confiance.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'rdv_reminder_'.$this->type,
            'title' => $this->type === '2h' ? 'RDV dans 2h' : 'Rappel RDV J-1',
            'message' => 'Votre intervention est prévue le '.$this->rendezVous->date?->format('d/m/Y').' à '.substr((string) $this->rendezVous->heure, 0, 5).'.',
            'rendez_vous_id' => $this->rendezVous->id,
            'url' => route('client.rendezvous.index'),
        ];
    }
}