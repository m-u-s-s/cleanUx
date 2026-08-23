<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\InsuranceClaim;
use App\Services\Nps\NpsService;
use App\Services\Payments\CommissionService;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Client — Profile
 *
 * @authenticated
 */
class ClientProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|nullable|string|max:30',
            'locale' => 'sometimes|string|in:fr,nl,en',
        ]);

        $request->user()->update($data);

        return response()->json(['ok' => true, 'user' => $request->user()->fresh()]);
    }

    /** L'avatar atterrit sur le disque `public`, donc dans un dossier servi tel quel sur le domaine de l'application. */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ImagesTeleversees::regles(tailleMaxKo: 5120),
        ]);

        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['profile_photo_path' => $path]);

        return response()->json(['ok' => true, 'avatar_url' => asset('storage/'.$path)]);
    }

    public function npsSimplified(Request $request): JsonResponse
    {
        $data = $request->validate(['score' => 'required|integer|min:0|max:10']);

        try {
            app(NpsService::class)->submit(
                user: $request->user(),
                surveyCode: 'monthly',
                score: (int) $data['score'],
            );
        } catch (\Throwable $e) {
            Log::warning('NPS submit error: '.$e->getMessage());
        }

        return response()->json(['ok' => true]);
    }

    public function claimsMine(Request $request): JsonResponse
    {
        $claims = InsuranceClaim::whereHas(
            'insurance',
            fn ($q) => $q->where('user_id', $request->user()->id)
        )
            ->with(['insurance.plan'])
            ->orderByDesc('filed_at')
            ->get();

        return response()->json(['data' => $claims]);
    }

    public function commissionPreview(Request $request, Booking $booking): JsonResponse
    {
        $clientId = (int) ($booking->client_id ?? $booking->customer_user_id ?? 0);
        abort_unless($clientId && $clientId === (int) $request->user()->id, 403);

        $calc = app(CommissionService::class)->calculateForBooking($booking);

        return response()->json(['ok' => true, ...$calc]);
    }
}
