<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderPayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Balance;
use Stripe\Stripe;

/**
 * Phase 13 — API Payouts pour le prestataire mobile.
 *
 * GET /api/provider/payouts            → historique paginé
 * GET /api/provider/payouts/summary    → solde + totaux mois en cours/passé
 * GET /api/provider/balance            → balance Stripe Connect (pending + available)
 *
 * Tous les endpoints sont scoped au user authentifié via ProviderProfile.
 */
class ProviderPayoutsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        $params = $request->validate([
            'status'   => ['nullable', 'in:pending,processing,paid,failed'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ]);

        $query = ProviderPayout::query()
            ->forProvider($user->id)
            ->orderByDesc('created_at');

        if (! empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (! empty($params['from'])) {
            $query->whereDate('created_at', '>=', $params['from']);
        }
        if (! empty($params['to'])) {
            $query->whereDate('created_at', '<=', $params['to']);
        }

        $perPage = (int) ($params['per_page'] ?? 20);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'ok'         => true,
            'data'       => collect($paginator->items())->map(fn ($p) => $this->serialize($p))->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        $base = ProviderPayout::query()->forProvider($user->id);

        $thisMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd   = now()->subMonth()->endOfMonth();

        $thisMonthPaid    = (clone $base)->paid()->where('created_at', '>=', $thisMonthStart)->sum('amount');
        $thisMonthPending = (clone $base)->pending()->where('created_at', '>=', $thisMonthStart)->sum('amount');
        $lastMonthPaid    = (clone $base)->paid()->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum('amount');
        $totalPaid        = (clone $base)->paid()->sum('amount');
        $totalPending     = (clone $base)->pending()->sum('amount');

        return response()->json([
            'ok' => true,
            'currency' => 'EUR',
            'this_month' => [
                'paid_amount'    => round((float) $thisMonthPaid, 2),
                'pending_amount' => round((float) $thisMonthPending, 2),
            ],
            'last_month' => [
                'paid_amount' => round((float) $lastMonthPaid, 2),
            ],
            'all_time' => [
                'paid_amount'    => round((float) $totalPaid, 2),
                'pending_amount' => round((float) $totalPending, 2),
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->abortIfNotProvider($user);

        if (! $user->stripe_connect_account_id) {
            return response()->json([
                'ok'    => false,
                'error' => 'Compte Stripe Connect non configuré.',
            ], 400);
        }

        if (! ($key = config('cashier.secret'))) {
            return response()->json([
                'ok'    => false,
                'error' => 'Stripe non configuré côté serveur.',
            ], 500);
        }

        Stripe::setApiKey($key);

        try {
            $balance = Balance::retrieve(['stripe_account' => $user->stripe_connect_account_id]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'Échec récupération balance : ' . $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'balance' => [
                'available' => $this->normalizeBalance($balance->available ?? []),
                'pending'   => $this->normalizeBalance($balance->pending ?? []),
            ],
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function abortIfNotProvider($user): void
    {
        abort_if(
            ! $user || ! $user->providerProfile,
            403,
            'Vous devez être prestataire pour utiliser ces endpoints.'
        );
    }

    protected function serialize(ProviderPayout $p): array
    {
        return [
            'id'                  => $p->id,
            'amount'              => (float) $p->amount,
            'currency'            => $p->currency,
            'status'              => $p->status,
            'provider'            => $p->provider,
            'provider_payout_id'  => $p->provider_payout_id,
            'period_start'        => $p->period_start?->toDateString(),
            'period_end'          => $p->period_end?->toDateString(),
            'mission_id'          => $p->metadata['mission_id'] ?? null,
            'booking_reference'   => $p->metadata['booking_reference'] ?? null,
            'created_at'          => $p->created_at?->toIso8601String(),
            'updated_at'          => $p->updated_at?->toIso8601String(),
        ];
    }

    protected function normalizeBalance(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $currency = strtoupper($item->currency ?? 'EUR');
            $result[$currency] = round(((float) ($item->amount ?? 0)) / 100, 2);
        }
        return $result;
    }
}
