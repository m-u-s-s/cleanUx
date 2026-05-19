<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderWalletTransaction;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ProviderWalletController extends Controller
{
    public function balance(Request $request, ProviderWalletService $wallet): JsonResponse
    {
        $currency = (string) $request->query('currency', 'EUR');
        return response()->json($wallet->balance($request->user()->id, $currency));
    }

    public function transactions(Request $request): JsonResponse
    {
        $params = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'type' => ['nullable', 'string', 'max:64'],
        ]);

        $query = ProviderWalletTransaction::query()
            ->forProvider($request->user()->id)
            ->latest('occurred_at')
            ->latest('id');

        if (! empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        $items = $query->limit($params['limit'] ?? 50)->get();

        return response()->json([
            'data' => $items->map(fn (ProviderWalletTransaction $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'direction' => $t->direction,
                'amount' => (float) $t->amount,
                'currency' => $t->currency,
                'status' => $t->status,
                'description' => $t->description,
                'occurred_at' => $t->occurred_at,
                'source_type' => $t->source_type,
                'source_id' => $t->source_id,
            ]),
        ]);
    }

    public function withdraw(Request $request, ProviderWalletService $wallet): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $payout = $wallet->requestWithdraw(
                $request->user(),
                (float) $data['amount'],
                strtoupper($data['currency'] ?? 'EUR'),
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'payout_id' => $payout->id,
            'amount' => (float) $payout->amount,
            'currency' => $payout->currency,
            'status' => $payout->status,
        ], 201);
    }
}
