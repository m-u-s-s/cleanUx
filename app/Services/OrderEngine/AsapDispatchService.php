<?php

namespace App\Services\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\OrderDraftItem;
use App\Models\User;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La recherche d'un prestataire immédiat : ouvrir, élargir, accepter, annuler.
 *
 * Trois règles gouvernent tout ce fichier.
 *
 * L'ÉTAT NE SAUTE PAS. Les transitions sont écrites, pas devinées : une demande ne passe jamais de
 * « en recherche » à « terminée », et une intervention commencée ne s'annule plus — on la termine,
 * et le litige se règle après. Laisser annuler à ce stade priverait le prestataire d'un travail
 * déjà fourni.
 *
 * LES FRAIS S'ANNONCENT AVANT. `quoteCancellation()` existe pour que l'écran dise ce que
 * l'annulation coûtera AVANT que le client clique. Des frais découverts après le clic font perdre
 * un client pour de bon, et le montant récupéré ne compense jamais.
 *
 * JAMAIS DE CUL-DE-SAC. Quand personne ne répond, `waysForward()` propose d'élargir, de basculer
 * en rendez-vous planifié, ou d'être prévenu. Un écran d'attente qui finit sur « personne n'est
 * disponible » sans rien d'autre est un bug produit.
 */
class AsapDispatchService
{
    public function __construct(
        protected ProviderAvailabilityLookup $lookup,
    ) {}

    /**
     * Ouvre la recherche.
     *
     * Idempotent : rafraîchir l'écran ou revenir dessus ne lance pas une seconde recherche pour la
     * même ligne de commande — ce qui préviendrait deux fois les mêmes prestataires et pourrait
     * produire deux acceptations.
     */
    public function open(OrderDraftItem $item, float $lat, float $lng): AsapDispatchRequest
    {
        $existing = AsapDispatchRequest::query()
            ->where('order_draft_item_id', $item->id)
            ->open()
            ->first();

        if ($existing) {
            return $existing;
        }

        $radius = (int) Config::get('order_engine.asap_initial_radius_m', 5000);

        $request = AsapDispatchRequest::create([
            'order_draft_id' => $item->order_draft_id,
            'order_draft_item_id' => $item->id,
            'trade_id' => $item->trade_id,
            'status' => AsapStatus::SEARCHING,
            'lat' => $lat,
            'lng' => $lng,
            'radius_m' => $radius,
            'searching_at' => now(),
        ]);

        return $this->notifyWithinRadius($request);
    }

    /**
     * Élargit le rayon d'un palier.
     *
     * Borné : au-delà, on ne cherche plus, on l'annonce. Continuer d'élargir indéfiniment
     * enverrait un prestataire à quarante kilomètres pour une intervention d'une heure, et le
     * client attendrait une heure de trajet qu'il n'a pas demandée.
     */
    public function expand(AsapDispatchRequest $request): AsapDispatchRequest
    {
        if ($request->status !== AsapStatus::SEARCHING) {
            return $request;
        }

        $step = (int) Config::get('order_engine.asap_radius_step_m', 5000);
        $max = (int) Config::get('order_engine.asap_max_radius_m', 20000);

        if ($request->radius_m >= $max) {
            return $request;
        }

        $request->update([
            'radius_m' => min($request->radius_m + $step, $max),
            'expansion_count' => $request->expansion_count + 1,
        ]);

        return $this->notifyWithinRadius($request->fresh());
    }

    /**
     * La recherche a-t-elle assez duré ?
     *
     * Le délai est un choix produit, pas une limite technique : au-delà, mieux vaut proposer une
     * suite que de laisser quelqu'un devant un sablier.
     */
    public function hasTimedOut(AsapDispatchRequest $request): bool
    {
        if ($request->status !== AsapStatus::SEARCHING) {
            return false;
        }

        return $request->elapsedSeconds() >= (int) Config::get('order_engine.asap_timeout_seconds', 180);
    }

    /** Personne n'a répondu : on ferme la recherche, on n'abandonne pas le client. */
    public function expire(AsapDispatchRequest $request): AsapDispatchRequest
    {
        return $this->transition($request, AsapStatus::EXPIRED);
    }

    /** Relance une recherche expirée, avec un rayon élargi. */
    public function retry(AsapDispatchRequest $request): AsapDispatchRequest
    {
        $request = $this->transition($request, AsapStatus::SEARCHING);
        $request->update(['searching_at' => now()]);

        return $this->expand($request->fresh());
    }

    /**
     * Un prestataire prend la course.
     *
     * Verrou pessimiste : deux prestataires peuvent accepter à la même seconde, et le second doit
     * être refusé proprement plutôt que d'écraser le premier. Sans ce verrou, deux personnes
     * partiraient pour la même intervention et une seule serait payée.
     *
     * @throws ValidationException si la course a déjà été prise
     */
    public function accept(AsapDispatchRequest $request, User $provider): AsapDispatchRequest
    {
        return DB::transaction(function () use ($request, $provider) {
            $locked = AsapDispatchRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($locked->status !== AsapStatus::SEARCHING) {
                throw ValidationException::withMessages([
                    'dispatch' => ['Cette demande n’est plus disponible.'],
                ]);
            }

            $freeMinutes = (int) Config::get('order_engine.asap_free_cancellation_minutes', 3);

            $locked->update([
                'status' => AsapStatus::ACCEPTED,
                'accepted_by_user_id' => $provider->id,
                'accepted_at' => now(),
                /*
                 * La fenêtre est FIGÉE ici, pas recalculée à la lecture. Celle qu'on annonce au
                 * client est ainsi celle qui s'applique, même si la configuration change
                 * entre-temps.
                 */
                'free_cancellation_until' => now()->addMinutes($freeMinutes),
            ]);

            $this->handOverToBooking($locked, $provider);

            return $locked->fresh();
        });
    }

    /**
     * Ce que l'annulation coûterait MAINTENANT.
     *
     * Appelé pour AFFICHER, avant tout clic. C'est la différence entre un client qui décide et un
     * client qui découvre.
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

    /**
     * Annule, en appliquant exactement ce qui vient d'être annoncé.
     *
     * Le montant est relu de `quoteCancellation()` : l'écran et la facture ne peuvent pas diverger.
     */
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
     * Jamais moins d'une : un écran d'attente qui finit sur un constat est un bug produit.
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

    /**
     * La reprise : la recherche acceptée devient une intervention réelle.
     *
     * Sans ce passage, la course serait « acceptée » dans son propre coin pendant que la
     * réservation resterait en attente : le prestataire ne la verrait pas dans son application,
     * n'aurait ni mise en route, ni codes de démarrage et de fin, ni clôture — donc aucun
     * encaissement. C'est exactement le défaut corrigé côté flux historique.
     *
     * Passer la réservation en « confirmé » déclenche l'observateur qui crée la mission : on
     * réutilise le pont existant plutôt que d'en poser un second à côté.
     *
     * UN SEUL CANAL D'ASSIGNATION. Si la réservation porte déjà un autre prestataire, on ne
     * l'écrase pas — deux personnes partiraient pour la même intervention et une seule serait
     * payée. Le verrou de `accept()` protège la course ; celui-ci protège la réservation.
     *
     * @throws ValidationException si la réservation est déjà partie chez quelqu'un d'autre
     */
    protected function handOverToBooking(AsapDispatchRequest $request, User $provider): void
    {
        $bookingId = $request->item?->metadata['booking_id'] ?? null;

        // Pas de réservation : la recherche a été ouverte hors confirmation (test, admin). Rien à
        // reprendre, et ce n'est pas une erreur.
        if (! $bookingId) {
            return;
        }

        $booking = Booking::query()->lockForUpdate()->find($bookingId);

        if (! $booking) {
            return;
        }

        if ($booking->employe_id && (int) $booking->employe_id !== (int) $provider->id) {
            throw ValidationException::withMessages([
                'dispatch' => ['Cette intervention a déjà été attribuée à un autre professionnel.'],
            ]);
        }

        $booking->update([
            'employe_id' => $provider->id,
            'status' => BookingStatus::CONFIRME,
            'matched_at' => now(),
        ]);
    }

    /**
     * Compte les prestataires prévenus dans le rayon courant.
     *
     * Le nombre est REL : il vient de ceux dont on connaît la position et qui exercent le métier.
     * Un compteur qui monte tout seul rassurerait deux minutes puis détruirait la confiance au
     * premier client qui attend pour rien.
     */
    protected function notifyWithinRadius(AsapDispatchRequest $request): AsapDispatchRequest
    {
        $trade = $request->trade;

        if (! $trade || $request->lat === null || $request->lng === null) {
            return $request;
        }

        $count = $this->lookup->nearby($trade, $request->lat, $request->lng, $request->radius_m)->count();

        /*
         * Le compteur ne redescend jamais : un prestataire prévenu l'a été, même s'il s'éloigne
         * ensuite. Le voir baisser laisserait croire à un désistement.
         *
         * Le transtypage n'est pas décoratif : `max(null, 0)` rend NULL en PHP — les deux valeurs
         * étant tenues pour égales, `max` renvoie la première. Sur une colonne non nulle, une
         * recherche sans aucun prestataire faisait donc échouer l'écriture, et seulement celle-là.
         */
        $request->update(['notified_count' => max((int) $request->notified_count, $count)]);

        return $request->fresh();
    }
}
