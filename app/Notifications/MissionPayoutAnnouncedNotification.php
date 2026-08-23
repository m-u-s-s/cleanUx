<?php

namespace App\Notifications;

use App\Models\Mission;
use App\Support\Notifications\InteractsWithUserNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** LE PRESTATAIRE N'ÉTAIT PRÉVENU DE RIEN À LA CLÔTURE. */
class MissionPayoutAnnouncedNotification extends Notification
{
    use InteractsWithUserNotificationPreferences;
    use Queueable;

    /**
     * @param  array<string, mixed>  $annonce  Sortie de PayoutAnnouncementService::pour()
     */
    public function __construct(
        public Mission $mission,
        public array $annonce,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels(
            $notifiable,
            'mission_payout_announced',
            ['database', 'mail'],
        );
    }

    public function titre(): string
    {
        return 'Félicitations, vous avez terminé votre mission';
    }

    public function corps(): string
    {
        $montant = number_format((float) $this->annonce['montant_prestataire'], 2, ',', ' ');
        $date = Carbon::parse($this->annonce['date_transfert'])->translatedFormat('d F Y');

        return sprintf(
            'Félicitations, vous avez fini votre mission. %s € seront transférés sur votre compte le %s.',
            $montant,
            $date,
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->titre())
            ->greeting('Bravo,')
            ->line($this->corps())
            ->line('Référence de la mission : '.($this->mission->booking->booking_reference ?? $this->mission->id).'.')
            ->line('Commission Brio retenue : '.number_format((float) $this->annonce['commission_plateforme'], 2, ',', ' ').' €.')
            ->action('Voir mes revenus', url('/dashboard/provider'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mission_payout_announced',
            'mission_id' => $this->mission->id,
            'rendez_vous_id' => $this->mission->booking_id,
            'booking_reference' => $this->mission->booking?->booking_reference,
            'titre' => $this->titre(),
            'message' => $this->corps(),
            'montant_prestataire' => $this->annonce['montant_prestataire'],
            'commission_plateforme' => $this->annonce['commission_plateforme'],
            'date_transfert' => $this->annonce['date_transfert'],
            'devise' => $this->annonce['devise'],
        ];
    }
}
