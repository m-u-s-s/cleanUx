<?php

namespace App\Notifications;

use App\Models\FinanceInvoice;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FinanceReminderNotification extends Notification
{
    use Queueable;
    use InteractsWithUserNotificationPreferences;

    public function __construct(
        public FinanceInvoice $invoice,
        public string $reminderType = 'gentle'
    ) {
    }

    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable, 'finance_reminder', ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->reminderType) {
            'overdue' => 'CleanUx · Rappel de facture en retard',
            'final' => 'CleanUx · Dernier rappel avant relance',
            default => 'CleanUx · Rappel de facture',
        };

        return (new MailMessage)
            ->subject($subject)
            ->line('Un rappel est émis concernant votre facture ' . $this->invoice->invoice_number . '.')
            ->line('Montant total : ' . number_format((float) $this->invoice->total_amount, 2, ',', ' ') . ' €')
            ->line('Solde restant dû : ' . number_format((float) $this->invoice->balance_due, 2, ',', ' ') . ' €')
            ->line('Échéance : ' . optional($this->invoice->due_at)->format('d/m/Y'))
            ->action('Ouvrir mon espace client', url('/dashboard/client'));
    }

    public function toArray(object $notifiable): array
    {
        return $this->basePayload([
            'type' => 'finance',
            'severity' => $this->reminderType === 'final' ? 'danger' : 'warning',
            'title' => 'Rappel de facture',
            'message' => 'Rappel facture ' . $this->invoice->invoice_number,
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'reminder_type' => $this->reminderType,
            'balance_due' => (float) $this->invoice->balance_due,
            'due_at' => optional($this->invoice->due_at)->toDateString(),
            'action_url' => url('/dashboard/client'),
        ]);
    }
}
