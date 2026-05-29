<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;

/**
 * @group Client — Payment Methods
 *
 * @authenticated
 */
class PaymentMethodController extends Controller
{
    private function boot(): void
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->stripe_id) {
            return response()->json(['data' => []]);
        }

        $this->boot();

        $methods = PaymentMethod::all([
            'customer' => $user->stripe_id,
            'type' => 'card',
        ]);

        $data = collect($methods->data)->map(fn (PaymentMethod $pm) => [
            'id' => $pm->id,
            'brand' => $pm->card->brand,
            'last4' => $pm->card->last4,
            'exp_month' => $pm->card->exp_month,
            'exp_year' => $pm->card->exp_year,
            'is_default' => $pm->id === $user->pm_last_four,
        ]);

        return response()->json(['data' => $data]);
    }

    public function setupIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->boot();

        // Create Stripe customer if not yet created (Cashier convention)
        if (! $user->stripe_id) {
            $user->createAsStripeCustomer();
            $user->refresh();
        }

        $intent = SetupIntent::create([
            'customer' => $user->stripe_id,
            'payment_method_types' => ['card'],
        ]);

        return response()->json(['client_secret' => $intent->client_secret]);
    }

    public function destroy(Request $request, string $paymentMethodId): JsonResponse
    {
        $user = $request->user();

        $this->boot();

        // Retrieve and verify ownership before detaching
        $pm = PaymentMethod::retrieve($paymentMethodId);

        if ($pm->customer !== $user->stripe_id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $pm->detach();

        return response()->json(['ok' => true]);
    }
}
