<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\Promotion\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $service)
    {
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->service->statsForUser($user);

        return response()->json([
            'referral_code' => $stats['referral_code'],
            'invite_url' => url('/register?ref=' . urlencode($stats['referral_code'])),
            'stats' => [
                'total_invited' => $stats['total_invited'],
                'total_signed_up' => $stats['total_signed_up'],
                'total_qualified' => $stats['total_qualified'],
                'total_rewarded' => $stats['total_rewarded'],
                'total_earned' => $stats['total_earned'],
            ],
            'rewards' => [
                'per_qualified_referrer' => ReferralService::DEFAULT_REFERRER_REWARD,
                'per_qualified_referee' => ReferralService::DEFAULT_REFEREE_REWARD,
                'currency' => 'EUR',
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $code = $this->service->ensureReferralCode($user);
        $url = url('/register?ref=' . urlencode($code));

        $referral = Referral::create([
            'referrer_user_id' => $user->id,
            'referee_email' => $data['email'],
            'referral_code' => $code,
            'status' => Referral::STATUS_INVITED,
            'invited_at' => now(),
            'expires_at' => now()->addDays(ReferralService::REFERRAL_EXPIRY_DAYS),
            'currency' => 'EUR',
            'source_channel' => 'api_invite',
            'referrer_reward_amount' => ReferralService::DEFAULT_REFERRER_REWARD,
            'referee_reward_amount' => ReferralService::DEFAULT_REFEREE_REWARD,
        ]);

        try {
            Mail::raw(
                "Bonjour,\n\n".
                $user->name." vous invite à essayer CleanUx.\n\n".
                ($data['message'] ?? '')."\n\n".
                "Inscrivez-vous via ce lien : ".$url."\n".
                "Ou utilisez le code : ".$code,
                function ($message) use ($data, $user) {
                    $message->to($data['email'])
                        ->subject('CleanUx · '.$user->name." vous invite");
                }
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'referral_id' => $referral->id,
            'invite_url' => $url,
            'expires_at' => $referral->expires_at,
        ], 201);
    }

    public function list(Request $request): JsonResponse
    {
        $user = $request->user();

        $referrals = Referral::query()
            ->forReferrer($user->id)
            ->with(['referee:id,name,email'])
            ->latest()
            ->limit((int) $request->integer('limit', 20))
            ->get()
            ->map(fn (Referral $r) => [
                'id' => $r->id,
                'referee_email' => $r->referee_email,
                'referee_name' => $r->referee?->name,
                'status' => $r->status,
                'invited_at' => $r->invited_at,
                'signed_up_at' => $r->signed_up_at,
                'qualified_at' => $r->qualified_at,
                'rewarded_at' => $r->rewarded_at,
                'referrer_reward_amount' => (float) $r->referrer_reward_amount,
                'currency' => $r->currency,
            ]);

        return response()->json(['data' => $referrals]);
    }
}
