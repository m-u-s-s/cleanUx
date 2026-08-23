<?php

namespace App\Notifications;

use App\Models\ProviderOnboardingDocument;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** « VOTRE PERMIS EXPIRE DANS TROIS SEMAINES. */
class ProviderDocumentExpiringNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    public function __construct(
        public ProviderOnboardingDocument $document,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'provider_document_expiring',
            ['database', 'mail'],
        );
    }

    public function libelle(): string
    {
        return (string) config(
            "onboarding_documents.labels.{$this->document->document_type}.label",
            $this->document->document_type,
        );
    }

    public function joursRestants(): int
    {
        if ($this->document->expires_at === null) {
            return 0;
        }

        return max(0, (int) Carbon::today()->diffInDays($this->document->expires_at, false));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $jours = $this->joursRestants();

        return (new MailMessage)
            ->subject(sprintf('%s : à renouveler avant le %s', $this->libelle(), $this->dateLisible()))
            ->greeting('Bonjour,')
            ->line(sprintf(
                'Votre %s arrive à échéance le %s%s.',
                mb_strtolower($this->libelle()),
                $this->dateLisible(),
                $jours > 0 ? sprintf(' — dans %d jour%s', $jours, $jours > 1 ? 's' : '') : '',
            ))
            // La conséquence, pas seulement le fait : c'est elle qui fait agir.
            ->line('Passé cette date, les missions qui l’exigent cesseront de vous être proposées.')
            ->action('Mettre à jour mes documents', url('/dashboard/employe/conduite'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'provider_document_expiring',
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'label' => $this->libelle(),
            'expires_at' => $this->document->expires_at?->toDateString(),
            'days_left' => $this->joursRestants(),
            'url' => '/dashboard/employe/conduite',
        ];
    }

    private function dateLisible(): string
    {
        return $this->document->expires_at?->translatedFormat('j F Y') ?? '';
    }
}
