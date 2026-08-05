<?php

namespace App\Notifications\Nps;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite a client to complete a post-booking NPS survey.
 *
 * Sent automatically by SendNpsSurveys command X days after booking completion.
 * Uses both the database channel (in-app bell) and mail.
 */
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
            ->subject('Comment s\'etait votre experience avec CleanUx ?')
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

    /**
     * LE LIEN ÉTAIT MORT DANS LES DEUX BRANCHES (corrigé le 2026-08-05).
     *
     * `nps.survey` n'existe pas — la route s'appelle `client.nps.survey`. L'exception était donc
     * levée à chaque envoi, attrapée, et le repli renvoyait vers `/nps`, que RIEN ne sert non plus.
     * Chaque destinataire d'une enquête NPS recevait un lien vers un 404, sans que rien ne le
     * signale : le try/catch transformait une erreur bruyante en lien mort silencieux.
     *
     * Le repli est conservé — une notification ne doit pas échouer parce qu'une route bouge — mais
     * il pointe désormais vers une adresse qui existe.
     */
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
