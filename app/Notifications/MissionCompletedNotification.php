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
        /*
         * TROIS DÉFAUTS CORRIGÉS ICI LE 2026-08-05.
         *
         * 1. `url('/client/dashboard')` était un 404 : la route est `/dashboard/client`. Les deux
         *    occurrences envoyaient le client sur une page inexistante.
         * 2. Les chemins étaient écrits en dur, donc figés et invisibles à toute analyse.
         * 3. TROIS `->action()` se suivaient sur le même MailMessage, alors que Laravel n'en rend
         *    qu'UN — le dernier. « Voir la mission » et « Donner mon avis » n'ont jamais été
         *    affichés à personne ; seul le bouton de téléchargement l'était, et il retombait sur
         *    le 404 quand le rapport manquait.
         *
         * Un seul bouton, donc, pour l'action principale ; les autres destinations passent en
         * liens dans le corps du message, où elles sont réellement visibles.
         */
        $tableauDeBord = route('client.dashboard');
        $avis = route('client.feedback.create', $this->mission->rendez_vous_id);

        return (new MailMessage)
            ->subject('Votre mission est terminée')
            ->greeting('Bonjour,')
            ->line('La mission '.$this->mission->rendezVous?->booking_reference.' est terminée.')
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
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->rendez_vous_id,
            'booking_reference' => $this->mission->rendezVous?->booking_reference,
            'employee_name' => $this->mission->leadEmployee?->name,
            'status' => $this->mission->status,
        ];
    }
}
