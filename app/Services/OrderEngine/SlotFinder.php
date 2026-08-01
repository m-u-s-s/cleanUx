<?php

namespace App\Services\OrderEngine;

use App\Models\Trade;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Les créneaux d'une journée — les vrais, et la raison de ceux qui ne le sont pas.
 *
 * Un créneau indisponible n'est PAS caché. Le masquer laisserait le client devant une grille
 * trouée sans comprendre pourquoi, et il en conclurait que le service est vide ou cassé. Grisé et
 * expliqué — « aucun professionnel disponible » — il informe au lieu d'inquiéter, et il rend
 * lisibles ceux qui restent.
 *
 * La disponibilité est lue une seule fois par prestataire pour toute la journée, puis recoupée
 * avec la grille. Interroger chaque agenda pour chaque créneau multiplierait les appels par le
 * nombre d'heures ouvrées, pour un écran que le client regarde trois secondes.
 */
class SlotFinder
{
    public function __construct(
        protected ProviderAvailabilityLookup $lookup,
        protected AvailabilityService $availability,
    ) {}

    /**
     * @return list<array{
     *     start: Carbon, end: Carbon, available: bool,
     *     provider_count: int, reason: string|null
     * }>
     */
    public function forDay(Trade $trade, float $lat, float $lng, Carbon $day): array
    {
        $duration = max(30, (int) ($trade->estimated_duration_min ?? 60));
        $providers = $this->lookup->nearby($trade, $lat, $lng);

        // Aucun professionnel dans la zone : la grille reste affichée, mais elle dit pourquoi.
        // Une page vide laisserait croire à une panne.
        if ($providers->isEmpty()) {
            return $this->grid($day, $duration)
                ->map(fn (array $slot) => $slot + [
                    'available' => false,
                    'provider_count' => 0,
                    'reason' => 'Aucun professionnel ne couvre encore cette zone.',
                ])
                ->all();
        }

        $windows = $this->windowsByProvider($providers, $day);
        $leadTime = Carbon::now()->addHours((int) Config::get('order_engine.slot_lead_time_hours', 2));

        return $this->grid($day, $duration)
            ->map(function (array $slot) use ($windows, $leadTime) {
                /*
                 * Un créneau déjà passé, ou trop proche pour qu'un professionnel s'organise, est
                 * grisé avec SA raison — distincte de « personne n'est libre ». Confondre les deux
                 * ferait croire à un service saturé alors qu'il est simplement tard.
                 */
                if ($slot['start']->lt($leadTime)) {
                    return $slot + [
                        'available' => false,
                        'provider_count' => 0,
                        'reason' => 'Trop proche pour être organisé.',
                    ];
                }

                $count = $this->providersCovering($windows, $slot['start'], $slot['end']);

                return $slot + [
                    'available' => $count > 0,
                    'provider_count' => $count,
                    'reason' => $count > 0 ? null : 'Aucun professionnel disponible sur ce créneau.',
                ];
            })
            ->all();
    }

    /** Le premier créneau réellement libre de la journée, s'il y en a un. */
    public function firstAvailable(Trade $trade, float $lat, float $lng, Carbon $day): ?array
    {
        foreach ($this->forDay($trade, $lat, $lng, $day) as $slot) {
            if ($slot['available']) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * La grille horaire de la journée.
     *
     * Le pas et les bornes viennent de la configuration : ils relèvent de l'exploitation, pas du
     * code. Une plateforme qui ouvre à 7 h en été ne devrait pas demander un déploiement.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    protected function grid(Carbon $day, int $durationMin): Collection
    {
        $open = (int) Config::get('order_engine.slot_day_start_hour', 8);
        $close = (int) Config::get('order_engine.slot_day_end_hour', 18);
        $step = max(30, (int) Config::get('order_engine.slot_step_minutes', 60));

        $slots = collect();
        $cursor = $day->copy()->startOfDay()->setHour($open);
        $limit = $day->copy()->startOfDay()->setHour($close);

        while ($cursor->copy()->addMinutes($durationMin)->lte($limit)) {
            $slots->push([
                'start' => $cursor->copy(),
                'end' => $cursor->copy()->addMinutes($durationMin),
            ]);
            $cursor->addMinutes($step);
        }

        return $slots;
    }

    /**
     * Les fenêtres de disponibilité, lues UNE fois par prestataire pour toute la journée.
     *
     * @param  Collection<int, array{id: int, distance_m: int}>  $providers
     * @return Collection<int, list<array{start: Carbon, end: Carbon}>>
     */
    protected function windowsByProvider(Collection $providers, Carbon $day): Collection
    {
        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $sampleSize = (int) Config::get('order_engine.slot_provider_sample', 12);

        return $providers->take($sampleSize)->map(function (array $row) use ($from, $to) {
            $provider = User::find($row['id']);

            if (! $provider) {
                return [];
            }

            try {
                return collect($this->availability->getAvailableWindows($provider, $from, $to))
                    ->map(fn ($window) => $this->normaliseWindow($window))
                    ->filter()
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                /*
                 * Soft-fail : un agenda illisible retire un professionnel du compte, il ne fait pas
                 * tomber la page de réservation. Le client verra simplement moins de créneaux.
                 */
                Log::warning('[order_engine] agenda illisible au calcul des créneaux', [
                    'provider_id' => $row['id'],
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        })->values();
    }

    /**
     * @param  Collection<int, list<array{start: Carbon, end: Carbon}>>  $windows
     */
    protected function providersCovering(Collection $windows, Carbon $start, Carbon $end): int
    {
        return $windows->filter(function (array $providerWindows) use ($start, $end) {
            foreach ($providerWindows as $window) {
                // La fenêtre doit couvrir TOUTE la prestation, pas seulement en croiser le début :
                // un professionnel libre une demi-heure ne peut pas tenir une intervention de deux.
                if ($window['start']->lte($start) && $window['end']->gte($end)) {
                    return true;
                }
            }

            return false;
        })->count();
    }

    /**
     * Le format des fenêtres appartient au module Disponibilité : on le lit sans le présumer.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    protected function normaliseWindow(mixed $window): ?array
    {
        if (! is_array($window)) {
            return null;
        }

        $start = $window['start'] ?? $window['starts_at'] ?? null;
        $end = $window['end'] ?? $window['ends_at'] ?? null;

        if ($start === null || $end === null) {
            return null;
        }

        try {
            return ['start' => Carbon::parse($start), 'end' => Carbon::parse($end)];
        } catch (\Throwable) {
            return null;
        }
    }
}
