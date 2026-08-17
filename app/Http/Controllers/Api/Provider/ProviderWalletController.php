<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Provider — Wallet
 *
 * @authenticated
 */
class ProviderWalletController extends Controller
{
    /**
     * Abort with 403 if the authenticated user is not a provider.
     * Mirrors the same guard used by ProviderPayoutsController.
     */
    protected function abortIfNotProvider(?User $user): void
    {
        abort_if(
            ! $user || ! $user->providerProfile,
            403,
            'Vous devez être prestataire pour utiliser ces endpoints.'
        );
    }

    public function balance(Request $request, ProviderWalletService $wallet): JsonResponse
    {
        $this->abortIfNotProvider($request->user());

        // SANS PARAMETRE, ON REND SA MONNAIE A LUI. Le defaut `'EUR'` filtrait les ecritures
        // d'un prestataire paye en dirhams jusqu'a n'en trouver aucune : solde a zero, en silence.
        $currency = $request->query('currency');

        return response()->json($wallet->balance(
            $request->user()->id,
            is_string($currency) ? $currency : null,
        ));
    }

    public function transactions(Request $request): JsonResponse
    {
        $this->abortIfNotProvider($request->user());
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
        $this->abortIfNotProvider($request->user());

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $payout = $wallet->requestWithdraw(
                $request->user(),
                (float) $data['amount'],
                // Sans devise demandee, le service prend celle du portefeuille. Imposer `EUR`
                // ici faisait refuser tout retrait d'un prestataire paye dans une autre monnaie,
                // au motif d'un solde insuffisant qui ne l'etait pas.
                isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
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
