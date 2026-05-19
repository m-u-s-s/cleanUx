<?php

namespace App\Services\Rating;

use App\Events\Rating\RatingPublished;
use App\Events\Rating\RatingSubmitted;
use App\Models\Booking;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\Rating\RatingReceivedNotification;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class RatingService
{
    public const RATING_WINDOW_DAYS = 14;
    public const EDIT_WINDOW_HOURS = 24;

    public function __construct(protected RatingAggregationService $aggregator)
    {
    }

    /**
     * Submit a rating from client → provider or provider → client.
     * Auto-publish "blind" style: rating is hidden until the other party rated
     * too, OR until the rating window expires (whichever comes first).
     *
     * @param  array{rating:int, comment?:string|null, punctuality?:int|null, quality?:int|null, communication?:int|null, value?:int|null, is_public?:bool}  $payload
     */
    public function submit(Booking $booking, User $author, string $direction, array $payload): Feedback
    {
        $this->guardEligibility($booking, $author, $direction);

        $rating = (int) ($payload['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'La note doit être comprise entre 1 et 5.',
            ]);
        }

        $existing = $this->findExistingRating($booking, $direction);

        if ($existing && ! $this->isEditableNow($existing)) {
            throw ValidationException::withMessages([
                'rating' => "Cet avis ne peut plus être modifié (fenêtre d'édition de "
                    . self::EDIT_WINDOW_HOURS . "h dépassée).",
            ]);
        }

        return DB::transaction(function () use ($booking, $author, $direction, $payload, $rating, $existing) {
            $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
            $providerId = (int) ($booking->employe_id ?? $booking->assigned_provider_user_id ?? 0);
            $missionId = $booking->mission?->id;

            $data = [
                'rendez_vous_id' => $booking->id,
                'booking_id' => $booking->id,
                'mission_id' => $missionId,
                'client_id' => $clientId,
                'employe_id' => $providerId,
                'direction' => $direction,
                'rating' => $rating,
                'note' => $rating,
                'comment' => $payload['comment'] ?? null,
                'commentaire' => $payload['comment'] ?? null,
                'punctuality_score' => $payload['punctuality'] ?? null,
                'quality_score' => $payload['quality'] ?? null,
                'communication_score' => $payload['communication'] ?? null,
                'value_score' => $payload['value'] ?? null,
                'is_public' => $payload['is_public'] ?? true,
                'answered_at' => now(),
            ];

            if ($existing) {
                $existing->fill($data);
                if ($existing->status === Feedback::STATUS_PUBLISHED) {
                    $existing->save();
                    if ($direction === Feedback::DIRECTION_CLIENT_TO_PROVIDER) {
                        $this->aggregator->recalculateForProvider($providerId);
                    }
                    return $existing->fresh();
                }
                $existing->save();
                $feedback = $existing;
            } else {
                $data['status'] = Feedback::STATUS_PENDING;
                $feedback = Feedback::create($data);
            }

            ActivityLogger::log('rating.submitted', $feedback, [
                'direction' => $direction,
                'rating' => $rating,
                'booking_id' => $booking->id,
                'author_user_id' => $author->id,
            ]);

            RatingSubmitted::dispatch($feedback);

            $this->maybePublishPair($booking, $feedback);

            return $feedback->fresh();
        });
    }

    /**
     * Force-publish all pending ratings on a booking when the rating window expires.
     * Idempotent — safe to run repeatedly (e.g. from a daily scheduler).
     */
    public function publishExpiredPending(): int
    {
        $cutoff = now()->subDays(self::RATING_WINDOW_DAYS);

        $pending = Feedback::query()
            ->where('status', Feedback::STATUS_PENDING)
            ->where('answered_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($pending as $feedback) {
            $this->publishSingle($feedback);
            $count++;
        }

        return $count;
    }

    protected function guardEligibility(Booking $booking, User $author, string $direction): void
    {
        if (! in_array($direction, [
            Feedback::DIRECTION_CLIENT_TO_PROVIDER,
            Feedback::DIRECTION_PROVIDER_TO_CLIENT,
        ], true)) {
            throw ValidationException::withMessages(['direction' => 'Direction invalide.']);
        }

        if (! $this->bookingCompleted($booking)) {
            throw ValidationException::withMessages([
                'rating' => "Vous ne pouvez noter qu'une prestation terminée.",
            ]);
        }

        $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
        $providerId = (int) ($booking->employe_id ?? $booking->assigned_provider_user_id ?? 0);

        if ($direction === Feedback::DIRECTION_CLIENT_TO_PROVIDER && (int) $author->id !== $clientId) {
            throw ValidationException::withMessages([
                'rating' => "Seul le client peut noter le prestataire.",
            ]);
        }
        if ($direction === Feedback::DIRECTION_PROVIDER_TO_CLIENT && (int) $author->id !== $providerId) {
            throw ValidationException::withMessages([
                'rating' => "Seul le prestataire peut noter le client.",
            ]);
        }

        if ($booking->mission_finished_at && $booking->mission_finished_at->lt(now()->subDays(self::RATING_WINDOW_DAYS))) {
            throw ValidationException::withMessages([
                'rating' => "Le délai de " . self::RATING_WINDOW_DAYS . " jours pour noter cette mission est dépassé.",
            ]);
        }
    }

    protected function findExistingRating(Booking $booking, string $direction): ?Feedback
    {
        return Feedback::query()
            ->where('booking_id', $booking->id)
            ->where('direction', $direction)
            ->first();
    }

    protected function isEditableNow(Feedback $feedback): bool
    {
        if ($feedback->status === Feedback::STATUS_PUBLISHED) {
            $window = ($feedback->published_at ?? $feedback->answered_at);
            return $window && $window->gt(now()->subHours(self::EDIT_WINDOW_HOURS));
        }
        return true;
    }

    protected function maybePublishPair(Booking $booking, Feedback $feedback): void
    {
        $clientRating = Feedback::query()
            ->where('booking_id', $booking->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->first();

        $providerRating = Feedback::query()
            ->where('booking_id', $booking->id)
            ->where('direction', Feedback::DIRECTION_PROVIDER_TO_CLIENT)
            ->first();

        if ($clientRating && $providerRating) {
            foreach ([$clientRating, $providerRating] as $rating) {
                if ($rating->status !== Feedback::STATUS_PUBLISHED) {
                    $this->publishSingle($rating);
                }
            }
        }
    }

    protected function publishSingle(Feedback $feedback): void
    {
        $feedback->update([
            'status' => Feedback::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        if ($feedback->isClientToProvider() && $feedback->employe_id) {
            $this->aggregator->recalculateForProvider((int) $feedback->employe_id);
        }

        RatingPublished::dispatch($feedback);

        try {
            $beneficiaryId = $feedback->isClientToProvider()
                ? $feedback->employe_id
                : $feedback->client_id;

            if ($beneficiaryId) {
                $beneficiary = User::find($beneficiaryId);
                if ($beneficiary) {
                    $beneficiary->notify(new RatingReceivedNotification($feedback));
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function bookingCompleted(Booking $booking): bool
    {
        return in_array($booking->status, [
            BookingStatus::TERMINE,
            'completed',
            'done',
        ], true);
    }
}
