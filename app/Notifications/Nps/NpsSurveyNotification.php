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
            ->greeting('Bonjour ' . $notifiable->name . ',')
            ->line('Votre mission du ' . $this->booking->scheduled_date . ' est terminee. Votre avis nous aide a ameliorer notre service.')
            ->action('Donner mon avis (30 secondes)', $surveyUrl)
            ->line('Merci pour votre retour !');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'nps_survey',
            'title'      => 'Donnez votre avis',
            'message'    => 'Votre mission est terminee. Partagez votre experience en 30 secondes.',
            'booking_id' => $this->booking->id,
            'url'        => $this->surveyUrl(),
        ];
    }

    private function surveyUrl(): string
    {
        try {
            return route('nps.survey', ['survey' => 'post_booking', 'bookingId' => $this->booking->id]);
        } catch (\Throwable) {
            return url('/nps?survey=post_booking&bookingId=' . $this->booking->id);
        }
    }
}
