<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Services\Insurance\InsurancePricingEngine;
use App\Services\Insurance\InsuranceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InsuranceController extends Controller
{
    public function __construct(
        protected InsuranceService $service,
        protected InsurancePricingEngine $pricing,
    ) {}

    public function plansForBooking(Request $request, int $booking): JsonResponse
    {
        $available = $this->pricing->getAvailablePlansForBooking($booking);

        return response()->json([
            'data' => array_map(fn ($item) => [
                'plan' => [
                    'code' => $item['plan']->code,
                    'name' => $item['plan']->name,
                    'description' => $item['plan']->description,
                    'coverage_amount_cents' => (int) $item['plan']->coverage_amount_cents,
                ],
                'premium_cents' => $item['premium_cents'],
                'currency' => $item['currency'],
            ], $available),
        ]);
    }

    public function purchase(Request $request, int $booking): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:64'],
        ]);

        try {
            $insurance = $this->service->purchase(
                bookingId: $booking,
                planCode: $data['plan_code'],
                user: $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'insurance' => $insurance,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $insurances = BookingInsurance::query()
            ->with('plan:id,code,name')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('purchased_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $insurances]);
    }

    public function cancel(Request $request, BookingInsurance $insurance): JsonResponse
    {
        if ($insurance->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $cancelled = $this->service->cancel($insurance);

        return response()->json([
            'ok' => true,
            'insurance' => $cancelled,
        ]);
    }

    public function fileClaim(Request $request, BookingInsurance $insurance): JsonResponse
    {
        if ($insurance->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'incident_type' => ['required', 'in:damage,theft,injury,liability,other,fraud_simulation'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'amount_claimed_cents' => ['required', 'integer', 'min:100'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string', 'max:500'],
        ]);

        try {
            $claim = $this->service->fileClaim(
                insurance: $insurance,
                claimant: $request->user(),
                incidentType: $data['incident_type'],
                description: $data['description'],
                incidentDate: \Carbon\Carbon::parse($data['incident_date']),
                amountClaimedCents: (int) $data['amount_claimed_cents'],
                evidence: $data['evidence'] ?? [],
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'claim' => $claim], 201);
    }

    public function listClaims(Request $request, BookingInsurance $insurance): JsonResponse
    {
        if ($insurance->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $claims = InsuranceClaim::query()
            ->where('booking_insurance_id', $insurance->id)
            ->orderByDesc('filed_at')
            ->get();

        return response()->json(['data' => $claims]);
    }
}
