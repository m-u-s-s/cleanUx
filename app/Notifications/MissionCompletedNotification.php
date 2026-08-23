<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Support\Media\PrivateMedia;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MissionCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Mission $mission) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // TROIS DÉFAUTS CORRIGÉS ICI LE 2026-08-05. 1.
        $tableauDeBord = route('client.dashboard');
        $avis = route('client.feedback.create', $this->mission->booking_id);

        return (new MailMessage)
            ->subject('Merci d’avoir fait confiance à Brio')
            ->greeting('Merci d’avoir fait confiance à Brio,')
            ->line('La mission '.$this->mission->booking?->booking_reference.' est terminée.')
            ->line('Vous pouvez maintenant valider la présence ou signaler un problème.')
            // M3 — report is on the private disk; link via a longer-lived signed URL (7 days),
            // still behind auth (login is preserved through the signed-route redirect).
            ->action('Télécharger le rapport', PrivateMedia::url($this->mission->report_path, 60 * 24 * 7) ?? $tableauDeBord)
            ->line('Donner mon avis sur cette mission : '.$avis)
            ->line('Retrouver la mission dans votre espace : '.$tableauDeBord);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_completed',
            'titre' => 'Merci d’avoir fait confiance à Brio',
            'message' => 'Merci d’avoir fait confiance à Brio. Votre mission est terminée — votre avis nous aide à garder le meilleur niveau.',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
        ];
    }
}
