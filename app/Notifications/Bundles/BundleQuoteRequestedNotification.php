<?php

namespace App\Notifications\Bundles;

use App\Models\MultiTradeBundleItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Bundle Marketplace P1 — demande de devis envoyée à un prestataire sollicité
 * pour un item de chantier groupé. Le prestataire peut proposer son prix.
 */
class BundleQuoteRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public MultiTradeBundleItem $item,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $bundle = $this->item->bundle;

        return (new MailMessage)
            ->subject('Nouvelle demande de devis — '.$this->item->label)
            ->greeting('Bonjour,')
            ->line("Un client recherche un prestataire pour : {$this->item->label}.")
            ->line('Proposez votre prix avant la fin du délai pour être sélectionné.')
            ->action('Voir la demande', route('employe.bundle-quotes'))
            ->line('Référence chantier : '.($bundle?->code ?? '—'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bundle.quote_requested',
            'bundle_item_id' => $this->item->id,
            'bundle_id' => $this->item->bundle_id,
            'trade_id' => $this->item->trade_id,
            'label' => $this->item->label,
        ];
    }
}
