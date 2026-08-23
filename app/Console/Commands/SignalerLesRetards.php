<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Missions\MissionDelayService;
use App\Support\Domain\BookingStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** LE BALAYAGE QUI DÉCLENCHE L'ANNONCE. */
class SignalerLesRetards extends Command
{
    protected $signature = 'missions:signaler-les-retards {--heures=12 : Profondeur du balayage} {--limite=500 : Nombre maximum de reservations examinees}';

    protected $description = 'Prevenir les clients dont le prestataire est en retard, une fois par reservation';

    public function handle(MissionDelayService $retards): int
    {
        $tolerance = max(0, (int) Config::get('missions.late_tolerance_minutes', 15));
        $borneHaute = Carbon::now()->subMinutes($tolerance);
        $borneBasse = Carbon::now()->subHours(max(1, (int) $this->option('heures')));
        $limite = max(1, (int) $this->option('limite'));

        $candidats = Booking::query()
            ->whereNull('late_notified_at')
            ->whereIn('status', [BookingStatus::EN_ATTENTE, BookingStatus::CONFIRME, BookingStatus::EN_ROUTE])
            ->where(function ($requete) use ($borneBasse, $borneHaute) {
                $requete
                    ->where(function ($avec) use ($borneBasse, $borneHaute) {
                        $avec->whereNotNull('scheduled_at')
                            ->whereBetween('scheduled_at', [$borneBasse, $borneHaute]);
                    })
                    ->orWhere(function ($sans) use ($borneBasse, $borneHaute) {
                        $sans->whereNull('scheduled_at')
                            ->whereBetween('date', [$borneBasse->copy()->startOfDay(), $borneHaute->copy()->endOfDay()]);
                    });
            })
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $annonces = 0;

        foreach ($candidats as $booking) {
            if ($retards->annoncerAuClient($booking)) {
                $annonces++;
            }
        }

        $this->info("Retards signales : {$annonces} (sur {$candidats->count()} reservations examinees)");

        return self::SUCCESS;
    }
}
