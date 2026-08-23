<?php

namespace App\Services\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Services\Dispatch\DispatchEngine;
use App\Support\Domain\AsapStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;

/** LE CÔTÉ CLIENT d'une recherche immédiate : élargir, expirer, annoncer le coût, annuler. */
class AsapDispatchService
{
    /** Élargit le rayon d'un palier, et relance la recherche depuis le moteur. */
    public function expand(AsapDispatchRequest $request): AsapDispatchRequest
    {
        if ($request->status !== AsapStatus::SEARCHING) {
            return $request;
        }

        $step = (int) Config::get('dispatch.waves.step_m', Config::get('order_engine.asap_radius_step_m', 5000));
        $max = (int) Config::get('dispatch.waves.max_radius_m', Config::get('order_engine.asap_max_radius_m', 20000));

        if ($request->radius_m >= $max) {
            return $request;
        }

        $request->update([
            'radius_m' => min($request->radius_m + $step, $max),
            'wave' => (int) $request->wave + 1,
            'expansion_count' => $request->expansion_count + 1,
        ]);

        app(DispatchEngine::class)->offerNext($request->fresh());

        return $request->fresh();
    }

    /** La recherche a-t-elle assez duré ? */
    public function hasTimedOut(AsapDispatchRequest $request): bool
    {
        if ($request->status !== AsapStatus::SEARCHING) {
            return false;
        }

        // L'ÉCHÉANCE EST ÉCRITE SUR LA RECHERCHE, pas recalculée à la lecture.
        if ($request->deadline_at !== null) {
            return $request->deadline_at->isPast();
        }

        return $request->elapsedSeconds() >= (int) Config::get('dispatch.search_deadline_seconds', 300);
    }

    /** Personne n'a répondu : on ferme la recherche, on n'abandonne pas le client. */
    public function expire(AsapDispatchRequest $request): AsapDispatchRequest
    {
        return $this->transition($request, AsapStatus::EXPIRED);
    }

    /** Relance une recherche expirée, avec un rayon élargi — « continuer à attendre ». */
    public function retry(AsapDispatchRequest $request): AsapDispatchRequest
    {
        $request = $this->transition($request, AsapStatus::SEARCHING);

        return app(DispatchEngine::class)->relaunch($request->fresh());
    }

    /**
     * Ce que l'annulation coûterait MAINTENANT. Appelé pour AFFICHER, avant tout clic.
     *
     * @return array{free: bool, fee_cents: int, reason: string, free_seconds_left: int|null}
     */
    public function quoteCancellation(AsapDispatchRequest $request): array
    {
        if ($request->status === AsapStatus::SEARCHING) {
            return [
                'free' => true,
                'fee_cents' => 0,
                'reason' => 'Personne ne s’est encore déplacé : l’annulation est gratuite.',
                'free_seconds_left' => null,
            ];
        }

        if ($request->cancellationIsFree()) {
            $secondsLeft = (int) now()->diffInSeconds($request->free_cancellation_until, false);

            return [
                'free' => true,
                'fee_cents' => 0,
                'reason' => sprintf('Annulation gratuite pendant encore %d s.', max(0, $secondsLeft)),
                'free_seconds_left' => max(0, $secondsLeft),
            ];
        }

        $fee = (int) Config::get('order_engine.asap_cancellation_fee_cents', 500);

        return [
            'free' => false,
            'fee_cents' => $fee,
            'reason' => sprintf(
                'Le professionnel est en route : l’annulation coûte %s €.',
                number_format($fee / 100, 2, ',', ' '),
            ),
            'free_seconds_left' => 0,
        ];
    }

    /** Annule, en appliquant exactement ce qui vient d'être annoncé. */
    public function cancel(AsapDispatchRequest $request, string $by = 'client', ?string $reason = null): AsapDispatchRequest
    {
        $quote = $this->quoteCancellation($request);
        $cancelled = $this->transition($request, AsapStatus::CANCELLED);

        $cancelled->update([
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'cancellation_reason' => $reason,
            'cancellation_fee_cents' => $quote['fee_cents'],
        ]);

        return $cancelled->fresh();
    }

    /** Avance d'un état, ou refuse. */
    public function transition(AsapDispatchRequest $request, string $to): AsapDispatchRequest
    {
        if (! AsapStatus::canMove($request->status, $to)) {
            throw ValidationException::withMessages([
                'dispatch' => [sprintf(
                    'Transition impossible : « %s » ne peut pas devenir « %s ».',
                    AsapStatus::label($request->status),
                    AsapStatus::label($to),
                )],
            ]);
        }

        // Chaque état laisse son horodatage : l'écran d'attente dit « en route depuis 2 min » sans
        // une jointure de plus à chaque rafraîchissement.
        $stamps = [
            AsapStatus::EN_ROUTE => 'en_route_at',
            AsapStatus::ARRIVED => 'arrived_at',
            AsapStatus::IN_PROGRESS => 'in_progress_at',
            AsapStatus::COMPLETED => 'completed_at',
        ];

        $attributes = ['status' => $to];

        if (isset($stamps[$to])) {
            $attributes[$stamps[$to]] = now();
        }

        $request->update($attributes);

        return $request->fresh();
    }

    /**
     * Les suites proposées quand personne ne répond.
     *
     * @return list<array{key: string, label: string, detail: string}>
     */
    public function waysForward(AsapDispatchRequest $request): array
    {
        $ways = [];
        $max = (int) Config::get('order_engine.asap_max_radius_m', 20000);

        if ($request->radius_m < $max) {
            $ways[] = [
                'key' => 'expand',
                'label' => 'Chercher plus loin',
                'detail' => sprintf(
                    'Élargir à %d km — le professionnel mettra un peu plus de temps à arriver.',
                    (int) round(min($request->radius_m + (int) Config::get('order_engine.asap_radius_step_m', 5000), $max) / 1000),
                ),
            ];
        }

        $ways[] = [
            'key' => 'schedule',
            'label' => 'Prendre rendez-vous',
            'detail' => 'Choisir un créneau au premier moment où quelqu’un est disponible.',
        ];

        $ways[] = [
            'key' => 'notify',
            'label' => 'Être prévenu',
            'detail' => 'Nous vous alertons dès qu’un professionnel se libère près de chez vous.',
        ];

        return $ways;
    }
}
