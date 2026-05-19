<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PriceQuote;
use App\Models\ServiceCatalogV2;
use App\Services\PricingV2\PricingEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PricingV2Controller extends Controller
{
    public function __construct(protected PricingEngine $engine)
    {
    }

    public function services(Request $request): JsonResponse
    {
        $rows = ServiceCatalogV2::query()
            ->active()
            ->when($request->filled('trade_code'), fn ($q) => $q->where('trade_code', $request->string('trade_code')))
            ->orderBy('code')
            ->get([
                'code', 'name', 'description', 'trade_code',
                'base_price_cents', 'currency', 'unit',
                'min_price_cents', 'max_price_cents',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function preview(Request $request): JsonResponse
    {
        if (! $this->passRateLimit($request)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:64'],
            'variables' => ['nullable', 'array'],
        ]);

        try {
            $preview = $this->engine->preview(
                $data['service_code'],
                (array) ($data['variables'] ?? []),
                $request->user(),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'preview' => $preview]);
    }

    public function quote(Request $request): JsonResponse
    {
        if (! $this->passRateLimit($request)) {
            return response()->json(['ok' => false, 'error' => 'rate_limited'], 429);
        }

        $data = $request->validate([
            'service_code' => ['required', 'string', 'max:64'],
            'variables' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'booking_id' => ['nullable', 'integer'],
        ]);

        try {
            $row = $this->engine->quote(
                serviceCode: $data['service_code'],
                variables: (array) ($data['variables'] ?? []),
                user: $request->user(),
                idempotencyKey: $data['idempotency_key'] ?? null,
                bookingId: $data['booking_id'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'quote' => $row], 201);
    }

    public function adminQuotes(Request $request): JsonResponse
    {
        $rows = PriceQuote::query()
            ->with('user:id,email')
            ->when($request->filled('service_code'), fn ($q) => $q->where('service_code', $request->string('service_code')))
            ->orderByDesc('quoted_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }

    protected function passRateLimit(Request $request): bool
    {
        $max = (int) config('pricing_v2.quote_rate_limit_per_minute', 60);
        $key = 'pricing:quote:' . sha1((string) $request->ip());
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return false;
        }
        RateLimiter::hit($key, 60);
        return true;
    }
}
