<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\NpsResponse;
use App\Models\User;
use App\Notifications\Nps\NpsSurveyNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Send NPS survey notifications to clients whose booking was completed
 * X days ago and who have not yet received a survey invitation.
 *
 * "Received" is tracked by checking for an existing NpsResponse row for the
 * booking. This avoids needing a new DB column and respects the idempotency
 * already built into NpsService.
 *
 * Registered as a daily cron in app/Console/Kernel.php.
 */
class SendNpsSurveys extends Command
{
    protected $signature = 'nps:send-surveys
                            {--days=3 : Days after completion before sending the survey}
                            {--limit=500 : Max bookings to process per run}
                            {--dry-run : List eligible bookings without sending}';

    protected $description = 'Send post-booking NPS survey notifications to eligible clients.';

    public function handle(): int
    {
        $daysAfter = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $isDryRun = (bool) $this->option('dry-run');

        $targetDate = now()->subDays($daysAfter)->toDateString();

        $bookings = Booking::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', $targetDate)
            ->whereNotNull('customer_user_id')
            ->limit($limit)
            ->get();

        if ($bookings->isEmpty()) {
            $this->info("No completed bookings found for date {$targetDate}.");

            return Command::SUCCESS;
        }

        $this->info("Checking {$bookings->count()} booking(s) completed on {$targetDate}".($isDryRun ? ' (dry-run)' : '').'.');

        $alreadySurveyed = NpsResponse::query()
            ->whereIn('booking_id', $bookings->pluck('id'))
            ->where('survey_code', 'post_booking')
            ->pluck('booking_id')
            ->flip();

        $sent = 0;
        $skip = 0;
        $failed = 0;

        foreach ($bookings as $booking) {
            if ($alreadySurveyed->has($booking->id)) {
                $skip++;

                continue;
            }

            $user = $booking->customer;
            if (! $user instanceof User) {
                $skip++;

                continue;
            }

            if ($isDryRun) {
                $this->line("  [dry] Booking #{$booking->id} → User #{$user->id} ({$user->email})");
                $sent++;

                continue;
            }

            try {
                $user->notify(new NpsSurveyNotification($booking));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[nps] Failed to send survey notification', [
                    'booking_id' => $booking->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Sent: {$sent}, Skipped (already surveyed): {$skip}, Failed: {$failed}.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
