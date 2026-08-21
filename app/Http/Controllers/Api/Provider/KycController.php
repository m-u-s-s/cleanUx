<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Services\Kyc\KycVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Provider — KYC
 *
 * @authenticated
 */
class KycController extends Controller
{
    public function __construct(protected KycVerificationService $service) {}

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'country_code' => ['nullable', 'string', 'size:2'],
            'checks' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        try {
            $verification = $this->service->start(
                $user,
                $data['country_code'] ?? null,
                $data['checks'] ?? [],
            );
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'verification_id' => $verification->id,
            'provider' => $verification->provider,
            'status' => $verification->status,
            'decision' => $verification->decision,
            'hosted_flow_url' => data_get($verification->metadata, 'hosted_flow_url'),
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $verification = KycVerification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        if (! $verification) {
            return response()->json([
                'has_verification' => false,
                'verified' => $this->estVerifie($request),
                'provider_verification_status' => $request->user()->providerProfile?->verification_status,
            ]);
        }

        return response()->json([
            'has_verification' => true,
            /*
             * L'ETAT, EN UN MOT, PARCE QUE C'EST CE QUE L'ECRAN DEMANDE.
             *
             * `KYCScreen` teste `status.verified` depuis toujours ; cette reponse ne l'a jamais
             * envoye. La branche « pas encore verifie » etait donc la SEULE atteignable : releve a
             * l'ecran, un prestataire dont l'identite est validee lisait quand meme « Completez la
             * verification pour recevoir des missions », sous un badge qui disait le contraire.
             *
             * La source de verite reste `provider_profiles.verification_status`, celle que
             * `KycVerificationService::markProviderApproved()` ecrit. On ne la recalcule pas ici :
             * on la nomme.
             */
            'verified' => $this->estVerifie($request),
            'verification_id' => $verification->id,
            'provider' => $verification->provider,
            'status' => $verification->status,
            'decision' => $verification->decision,
            'score' => $verification->score !== null ? (float) $verification->score : null,
            'rejection_reason' => $verification->rejection_reason,
            'started_at' => $verification->started_at,
            'completed_at' => $verification->completed_at,
            'provider_verification_status' => $request->user()->providerProfile?->verification_status,
        ]);
    }

    /** L'identite est-elle validee ? La reponse tient dans le profil, pas dans le dernier controle. */
    private function estVerifie(Request $request): bool
    {
        return $request->user()?->providerProfile?->verification_status === 'verified';
    }

    public function sync(Request $request, KycVerification $verification): JsonResponse
    {
        abort_unless((int) $verification->user_id === (int) $request->user()->id, 403);

        try {
            $this->service->syncStatus($verification);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        $verification->refresh();

        return response()->json([
            'verification_id' => $verification->id,
            'status' => $verification->status,
            'decision' => $verification->decision,
        ]);
    }
}
