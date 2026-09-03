<?php

namespace App\Notifications;

use App\Models\PlatformSeatTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * QUELQU'UN VIENT D'ARMER UN TRANSFERT DU SIÈGE.
 *
 * C'est cette annonce qui rend le délai utile : sans elle, le titulaire découvrirait la perte du
 * siège une fois le transfert appliqué. Elle part même quand c'est lui qui l'a armé — un accusé
 * de réception qu'il attend ne coûte rien, celui qu'il n'attendait pas le sauve.
 */
class TransfertDeSiegeArme extends Notification
{
    use Queueable;

    public function __construct(public PlatformSeatTransfer $transfert) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // `database` en premier : c'est le seul canal qui ne dépend d'aucun tiers.
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vers = $this->transfert->to->email;

        return (new MailMessage)
            ->subject('⚠️ Transfert du siège de super-administrateur')
            ->greeting('Un transfert vient d’être armé')
            ->line('Le siège de super-administrateur doit passer à '.$vers.'.')
            ->line('Il prendra effet le '.$this->transfert->effective_at->format('d/m/Y à H:i').'.')
            ->line('**Si ce n’est pas vous**, annulez-le immédiatement et changez votre mot de passe.')
            ->action('Voir le siège', route('admin.siege'))
            ->line('Armé depuis '.($this->transfert->armed_ip ?? 'une adresse inconnue').'.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'seat_transfer_armed',
            'transfert_id' => $this->transfert->id,
            'vers' => $this->transfert->to?->email,
            'effectif_le' => $this->transfert->effective_at->toIso8601String(),
            'ip' => $this->transfert->armed_ip,
        ];
    }
}
