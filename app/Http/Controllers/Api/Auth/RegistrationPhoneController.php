<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Le premier écran de l'inscription prestataire demande le téléphone et le vérifie par SMS, avant le nom, l'email ou le mot de passe.
 *
 * @group Auth — Vérification du téléphone à l'inscription
 */
class RegistrationPhoneController extends Controller
{
    public function __construct(protected PhoneVerificationService $service) {}

    public function requestCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        try {
            $code = $this->service->sendRegistrationCode($data['phone']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'phone' => $code->phone,
            'expires_at' => $code->expires_at,
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string', 'min:4', 'max:8'],
        ]);

        try {
            $token = $this->service->verifyRegistrationCode($data['phone'], $data['code']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true,
            'phone_verification_token' => $token,
        ]);
    }
}
