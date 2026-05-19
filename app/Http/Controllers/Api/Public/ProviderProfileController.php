<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints publics pour profil + avis providers (consultable sans auth).
 */
class ProviderProfileController extends Controller
{
    public function show(User $provider): JsonResponse
    {
        if (! $provider->isProvider()) {
            abort(404);
        }

        $profile = $provider->providerProfile;
        if (! $profile || ! $profile->isActive() || ! $profile->isVerified()) {
            abort(404);
        }

        return response()->json([
            'id' => $provider->id,
            'name' => $provider->name,
            'photo_url' => $profile->photo_path ? \Illuminate\Support\Facades\Storage::url($profile->photo_path) : null,
            'bio' => $profile->bio,
            'trades' => $provider->trades->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'code' => $t->code,
            ]),
            'rating' => [
                'avg' => $profile->rating_avg !== null ? (float) $profile->rating_avg : null,
                'count' => (int) $profile->rating_count,
                'distribution' => $profile->rating_distribution,
                'dimensions' => $profile->rating_dimensions,
            ],
        ]);
    }

    public function ratings(Request $request, User $provider): JsonResponse
    {
        if (! $provider->isProvider()) {
            abort(404);
        }

        $params = $request->validate([
            'min_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'sort' => ['nullable', 'in:recent,highest,lowest'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Feedback::query()
            ->publiclyVisible()
            ->forProvider($provider->id)
            ->with(['client:id,name']);

        if (! empty($params['min_rating'])) {
            $minRating = (int) $params['min_rating'];
            $query->where(function ($q) use ($minRating) {
                $q->where('rating', '>=', $minRating)
                    ->orWhere('note', '>=', $minRating);
            });
        }

        $query = match ($params['sort'] ?? 'recent') {
            'highest' => $query->orderByDesc('rating')->orderByDesc('note')->latest('published_at'),
            'lowest' => $query->orderBy('rating')->orderBy('note')->latest('published_at'),
            default => $query->latest('published_at'),
        };

        $ratings = $query->limit($params['limit'] ?? 20)->get();

        return response()->json([
            'data' => $ratings->map(fn (Feedback $f) => [
                'id' => $f->id,
                'rating' => (int) ($f->rating ?? $f->note),
                'comment' => $f->effectiveComment(),
                'punctuality_score' => $f->punctuality_score,
                'quality_score' => $f->quality_score,
                'communication_score' => $f->communication_score,
                'value_score' => $f->value_score,
                'client_name' => $f->client?->name,
                'published_at' => $f->published_at,
                'provider_response' => $f->provider_response,
                'provider_responded_at' => $f->provider_responded_at,
            ]),
        ]);
    }
}
