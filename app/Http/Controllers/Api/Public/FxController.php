<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\Fx\FxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FxController extends Controller
{
    public function __construct(protected FxService $svc)
    {
    }

    /**
     * GET /api/fx/currencies
     */
    public function currencies(): JsonResponse
    {
        $rows = Currency::query()->active()->orderBy('sort_order')->orderBy('code')->get([
            'code', 'name', 'symbol', 'decimals',
        ]);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/fx/rates?base=EUR&quotes=USD,GBP
     */
    public function rates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base' => ['nullable', 'string', 'size:3'],
            'quotes' => ['required', 'string', 'max:512'],
        ]);

        $base = strtoupper($data['base'] ?? (string) config('fx.base_currency', 'EUR'));
        $quotes = collect(explode(',', $data['quotes']))
            ->map(fn ($q) => strtoupper(trim($q)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $out = [];
        foreach ($quotes as $quote) {
            $rate = $this->svc->getRate($base, $quote);
            if ($rate) {
                $out[] = [
                    'base' => $rate->base_currency,
                    'quote' => $rate->quote_currency,
                    'rate' => (float) $rate->rate,
                    'source' => $rate->source,
                    'fetched_at' => $rate->fetched_at?->toIso8601String(),
                ];
            }
        }

        return response()->json(['data' => $out]);
    }

    /**
     * POST /api/fx/convert
     */
    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0'],
            'from' => ['required', 'string', 'size:3'],
            'to' => ['required', 'string', 'size:3'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $conv = $this->svc->convert(
            amountCents: (int) $data['amount_cents'],
            sourceCurrency: $data['from'],
            targetCurrency: $data['to'],
            user: $request->user(),
            idempotencyKey: $data['idempotency_key'] ?? null,
        );

        return response()->json([
            'ok' => true,
            'conversion' => [
                'source_amount_cents' => (int) $conv->source_amount_cents,
                'source_currency' => $conv->source_currency,
                'target_amount_cents' => (int) $conv->target_amount_cents,
                'target_currency' => $conv->target_currency,
                'rate_used' => (float) $conv->rate_used,
                'fee_percent' => (float) $conv->fee_percent,
                'converted_at' => $conv->converted_at?->toIso8601String(),
            ],
        ]);
    }
}
