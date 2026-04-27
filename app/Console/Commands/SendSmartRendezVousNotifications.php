<?php

namespace App\Console\Commands;

use App\Models\RendezVous;
use App\Services\Notifications\SmartNotificationService;
use App\Support\Domain\BookingStatus;
use Illuminate\Console\Command;

class SendSmartRendezVousNotifications extends Command
{
    protected $signature = 'app:send-smart-rdv-notifications';

    protected $description = 'Send intelligent reminders and feedback invitations for rendez-vous.';

    public function handle(SmartNotificationService $notifications): int
    {
        $now = now();

        RendezVous::query()
            ->with('client')
            ->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME])
            ->whereNull('rappel_24h_envoye_at')
            ->whereDate('date', $now->copy()->addDay()->toDateString())
            ->chunkById(100, function ($items) use ($notifications) {
                foreach ($items as $rdv) {
                    $notifications->send24hReminder($rdv);
                }
            });

        RendezVous::query()
            ->with('client')
            ->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME])
            ->whereNull('rappel_2h_envoye_at')
            ->whereDate('date', $now->toDateString())
            ->whereBetween('heure', [
                $now->copy()->addMinutes(90)->format('H:i:s'),
                $now->copy()->addMinutes(150)->format('H:i:s'),
            ])
            ->chunkById(100, function ($items) use ($notifications) {
                foreach ($items as $rdv) {
                    $notifications->send2hReminder($rdv);
                }
            });

        RendezVous::query()
            ->with(['client', 'feedback'])
            ->where('status', BookingStatus::TERMINE)
            ->whereNull('feedback_demande_envoye_at')
            ->whereDoesntHave('feedback')
            ->chunkById(100, function ($items) use ($notifications) {
                foreach ($items as $rdv) {
                    $notifications->sendFeedbackInvite($rdv);
                }
            });

        $this->info('Smart notifications sent.');

        return self::SUCCESS;
    }
}