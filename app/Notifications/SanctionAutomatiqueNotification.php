<?php

namespace App\Notifications;

use App\Models\MissionDisputeSignal;
use App\Models\MissionFeatureSuspension;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** UN BLOCAGE AUTOMATIQUE VIENT DE TOMBER — et un humain doit le savoir le jour même. */
class SanctionAutomatiqueNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MissionFeatureSuspension $suspension,
        public MissionDisputeSignal $signal,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission.sanction_automatique',
            'suspension_id' => $this->suspension->id,
            'user_id' => $this->suspension->user_id,
            'feature' => $this->suspension->feature,
            'level' => $this->suspension->level,
            'permanent' => $this->suspension->estDefinitive(),
            'ends_at' => $this->suspension->ends_at?->toIso8601String(),
            'signal_id' => $this->signal->id,
            'verdict' => $this->signal->verdict,
            'title' => 'Sanction automatique posée',
            'message' => $this->phrase(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sanction automatique — '.$this->libelleOption())
            ->line($this->phrase())
            ->line('Motif : '.$this->suspension->reason)
            ->line('Cette sanction ne peut être levée que par un administrateur.');
    }

    private function phrase(): string
    {
        $duree = $this->suspension->estDefinitive()
            ? 'définitivement'
            : 'jusqu’au '.$this->suspension->ends_at->format('d/m/Y');

        return $this->libelleOption().' retirée '.$duree
            .' à l’utilisateur #'.$this->suspension->user_id.'.';
    }

    private function libelleOption(): string
    {
        return match ($this->suspension->feature) {
            MissionFeatureSuspension::OPTION_REVISION => 'Révision de devis',
            MissionFeatureSuspension::OPTION_COMMANDE => 'Passer commande',
            default => $this->suspension->feature,
        };
    }
}
