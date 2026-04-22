<?php

namespace App\Notifications;

use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouveauRendezVousNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(public $rdv)
    {
    }

    public function via($notifiable)
    {
        return $this->preferredChannels($notifiable, 'booking_created', ['mail', 'database']);
    }

    public function toMail($notifiable)
    {
        $priorite = ucfirst($this->rdv->priorite ?? 'normale');

        $mail = (new MailMessage)
            ->subject($this->rdv->priorite === 'urgente' ? 'CleanUx · Demande urgente de nettoyage' : 'CleanUx · Nouvelle demande de nettoyage')
            ->line('Une nouvelle demande d’intervention a été envoyée.')
            ->line('Client : ' . ($this->rdv->client->name ?? '—'))
            ->line('Service : ' . $this->rdv->service_display_name)
            ->line('Date : ' . $this->rdv->date . ' à ' . $this->rdv->heure)
            ->line('Adresse : ' . $this->rdv->location_display)
            ->line('Priorité : ' . $priorite)
            ->line('Animaux : ' . ($this->rdv->presence_animaux ? 'Oui' : 'Non'))
            ->line('Parking : ' . ($this->rdv->acces_parking ? 'Oui' : 'Non'))
            ->line('Matériel fourni : ' . ($this->rdv->materiel_fournit ? 'Oui' : 'Non'))
            ->line('Photos de référence : ' . (! empty($this->rdv->photos_reference) ? 'Oui' : 'Non'))
            ->action('Voir mes rendez-vous', url('/dashboard/employe'))
            ->line('Merci de confirmer ou refuser cette intervention rapidement.');

        if ($this->rdv->priorite === 'urgente') {
            $mail->line('⚠️ Cette demande a été marquée comme urgente.');
        }

        return $mail;
    }

    public function toArray($notifiable)
    {
        return $this->basePayload([
            'type' => $this->rdv->priorite === 'urgente' ? 'urgent' : 'rendezvous',
            'severity' => $this->rdv->priorite === 'urgente' ? 'danger' : 'info',
            'title' => $this->rdv->priorite === 'urgente' ? 'Nouvelle demande urgente' : 'Nouvelle demande de nettoyage',
            'message' => ($this->rdv->priorite === 'urgente' ? '🚨 Demande urgente : ' : 'Nouvelle demande de nettoyage : ') . $this->rdv->service_display_name,
            'rdv_id' => $this->rdv->id,
            'client' => $this->rdv->client->name ?? '—',
            'date' => $this->rdv->date,
            'heure' => $this->rdv->heure,
            'adresse' => $this->rdv->adresse,
            'ville' => $this->rdv->ville,
            'service_identifier' => $this->rdv->service_identifier_display,
            'service_label' => $this->rdv->service_display_name,
            'location_display' => $this->rdv->location_display,
            'priorite' => $this->rdv->priorite,
            'status' => $this->rdv->status,
            'has_photos' => ! empty($this->rdv->photos_reference),
            'zone_name' => $this->rdv->serviceZone?->name,
            'action_url' => url('/dashboard/employe'),
        ]);
    }
}
