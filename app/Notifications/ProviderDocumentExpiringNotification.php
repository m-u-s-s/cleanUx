<?php

namespace App\Notifications;

use App\Models\ProviderOnboardingDocument;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * « VOTRE PERMIS EXPIRE DANS TROIS SEMAINES. »
 *
 * Une pièce qui arrive à échéance ne prévient personne. Le prestataire découvre la chose au silence
 * de son téléphone, plusieurs jours après coup, et le support l'apprend par un appel agacé — c'est
 * l'angle mort connu de cette plateforme, transposé aux dates plutôt qu'aux dossiers incomplets.
 *
 * ELLE DIT LA DATE, ET CE QU'ELLE COÛTE. « Pensez à mettre à jour vos documents » ne fait agir
 * personne : ce qui fait agir, c'est de savoir qu'à partir du 12 mars on cesse de recevoir des
 * courses. Le lien mène directement à l'écran où l'on redépose.
 *
 * Catégorie `transactional` : ce n'est pas une information de confort, c'est une conséquence
 * directe sur le revenu de la personne.
 */
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
