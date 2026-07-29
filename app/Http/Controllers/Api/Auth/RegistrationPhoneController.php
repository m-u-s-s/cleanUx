<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Auth — Vérification du téléphone à l'inscription
 *
 * Le premier écran de l'inscription prestataire demande le téléphone et le vérifie par SMS, avant
 * le nom, l'email ou le mot de passe. Ces routes sont donc publiques : à ce stade, le compte
 * n'existe pas. Elles portent `throttle:otp` (5/min par IP, 20/h) et chaque envoi repasse par les
 * plafonds par numéro de SmsService — un endpoint public qui déclenche des SMS payants.
 *
 * La vérification réussie rend un jeton à usage unique, présenté ensuite à
 * `POST /api/auth/register` : c'est lui, et non un booléen envoyé par le client, qui autorise à
 * marquer le téléphone comme vérifié sur le compte créé.
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
