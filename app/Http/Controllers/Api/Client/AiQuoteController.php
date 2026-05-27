<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Trade;
use App\Services\Ai\PhotoQuoteEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Client — AI Quote
 * @authenticated
 */
class AiQuoteController extends Controller
{
    /**
     * POST /api/client/ai-quote/photo
     *
     * Body : multipart/form-data
     *   - photo : fichier image (PNG/JPG/WEBP, max 5MB)
     *   - trade_code : code du trade (ex: "peinture_interieure")
     *   - note : (optionnel) texte additionnel client
     */
    public function estimateFromPhoto(Request $request, PhotoQuoteEstimator $estimator): JsonResponse
    {
        $data = $request->validate([
            'photo' => ['required', 'file', 'image', 'max:5120'],   // 5 MB
            'trade_code' => ['required', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $trade = Trade::query()->where('code', $data['trade_code'])->first();
        if (! $trade) {
            return response()->json([
                'error' => 'trade_not_found',
                'message' => "Trade '{$data['trade_code']}' introuvable.",
            ], 404);
        }

        $file = $request->file('photo');
        $base64 = base64_encode(file_get_contents($file->getRealPath()));

        $result = $estimator->estimateFromPhoto($base64, $trade, $data['note'] ?? null);

        if ($result === null) {
            return response()->json([
                'error' => 'estimation_unavailable',
                'message' => "L'estimation IA n'est pas disponible (clé API manquante ou échec).",
            ], 503);
        }

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'error' => $result['error'] ?? 'estimation_failed',
                'reason' => $result['reason'] ?? null,
            ], 422);
        }

        return response()->json(['data' => $result]);
    }
}
