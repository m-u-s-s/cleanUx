<?php

namespace App\Notifications\Nps;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invite a client to complete a post-booking NPS survey. */
class NpsSurveyNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $surveyUrl = $this->surveyUrl();

        return (new MailMessage)
            ->subject('Comment s\'etait votre experience avec Brio ?')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Votre mission du '.$this->booking->scheduled_date.' est terminee. Votre avis nous aide a ameliorer notre service.')
            ->action('Donner mon avis (30 secondes)', $surveyUrl)
            ->line('Merci pour votre retour !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'nps_survey',
            'title' => 'Donnez votre avis',
            'message' => 'Votre mission est terminee. Partagez votre experience en 30 secondes.',
            'booking_id' => $this->booking->id,
            'url' => $this->surveyUrl(),
        ];
    }

    /** LE LIEN ÉTAIT MORT DANS LES DEUX BRANCHES (corrigé le 2026-08-05). */
    private function surveyUrl(): string
    {
        $parametres = ['survey' => 'post_booking', 'bookingId' => $this->booking->id];

        try {
            return route('client.nps.survey', $parametres);
        } catch (\Throwable) {
            return url('/dashboard/client/nps?'.http_build_query($parametres));
        }
    }
}
