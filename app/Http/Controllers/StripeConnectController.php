<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Payments\StripeConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;


class StripeConnectController extends Controller
{
    public function start(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        return redirect()->away($service->onboardingLink($user));
    }

    public function refresh(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        return redirect()->away($service->onboardingLink($user));
    }

    public function return(StripeConnectService $service): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user->isEmploye(), 403);

        $service->syncAccountStatus($user);

        return redirect()
            ->route('employe.dashboard')
            ->with('success', 'Votre compte Stripe Connect a été vérifié.');
    }
}