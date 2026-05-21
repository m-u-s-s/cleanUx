<?php

namespace App\Services\Ai;

use App\Models\Trade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'estimation de devis à partir d'une photo, via Claude Vision (Anthropic).
 *
 * Différenciation FORT vs Uber/Bolt/Helpling : permet à un client de prendre une
 * photo d'une pièce/d'un mur/d'une toiture et recevoir un devis ±15-20% instantané.
 *
 * Workflow :
 *   1. Client upload photo (multipart) + trade choisi
 *   2. PhotoQuoteEstimator envoie à Claude Vision avec system prompt par trade
 *   3. Claude renvoie JSON : { surface_m2, état, durée_estimée_min, prix_min_cents, prix_max_cents, confiance }
 *   4. CleanUx affiche le quote + permet booking direct avec prix locked
 *
 * Soft-fail : si pas de clé API ou pas de SDK, retourne null avec raison logged.
 */
class PhotoQuoteEstimator
{
    /**
     * Estime un devis depuis une photo encodée base64 + un trade.
     */
    public function estimateFromPhoto(string $base64Image, Trade $trade, ?string $userNote = null): ?array
    {
        $apiKey = (string) config('services.anthropic.key', env('ANTHROPIC_API_KEY', ''));
        if ($apiKey === '') {
            Log::info('[ai_quote] Anthropic API key missing, estimation skipped');
            return null;
        }

        $model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
        $tradeName = $trade->name ?? $trade->code;

        $systemPrompt = $this->systemPromptForTrade($tradeName);

        $userContent = [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $this->detectMimeType($base64Image),
                    'data' => $base64Image,
                ],
            ],
            [
                'type' => 'text',
                'text' => $userNote
                    ? "Note utilisateur : " . $userNote . "\n\nAnalyse cette photo et fournis un devis estimé selon les instructions du système."
                    : "Analyse cette photo et fournis un devis estimé selon les instructions du système.",
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model,
                    'max_tokens' => 1024,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userContent],
                    ],
                ]);

            if (! $response->ok()) {
                Log::warning('[ai_quote] anthropic API error', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $data = $response->json();
            $assistantText = data_get($data, 'content.0.text', '');

            return $this->parseJsonFromText($assistantText, $trade);
        } catch (\Throwable $e) {
            Log::warning('[ai_quote] estimateFromPhoto failed', [
                'trade' => $trade->code ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function systemPromptForTrade(string $tradeName): string
    {
        return <<<PROMPT
Tu es un expert estimateur en {$tradeName} pour la marketplace CleanUx (Belgique/France).
Tu reçois une photo. Analyse-la et fournis un devis estimé.

Tarifs de référence (en €) :
- Nettoyage : 25-35€/h, 2-4h par pièce moyenne
- Peinture intérieure : 25-40€/m² avec fournitures
- Plomberie : 50-90€/h, intervention 1-3h
- Électricité : 55-95€/h
- Toiture : 40-70€/m² réparation, 80-150€/m² remplacement
- Babysitting : 12-18€/h
- Jardinage : 25-45€/h
- Déménagement : 35-55€/h par déménageur

Format de réponse STRICTEMENT en JSON valide (pas de texte autour), structure :
{
  "surface_estimee_m2": <float ou null>,
  "etat_observe": "<string court>",
  "duree_estimee_min": <int>,
  "prix_min_cents": <int>,
  "prix_max_cents": <int>,
  "confiance": <0-100 int>,
  "observations": "<string court 2-3 phrases>",
  "options_suggerees": ["<string>", ...]
}

Si la photo n'est pas pertinente pour ce trade, retourne :
{ "error": "photo_not_relevant", "reason": "<explication>" }
PROMPT;
    }

    protected function detectMimeType(string $base64Image): string
    {
        // Détecte signature de l'image depuis les premiers bytes décodés
        $decoded = base64_decode(substr($base64Image, 0, 32), true);
        if ($decoded === false || strlen($decoded) < 4) {
            return 'image/jpeg';
        }
        $signature = substr($decoded, 0, 4);
        if (str_starts_with($signature, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($signature, "\xff\xd8")) {
            return 'image/jpeg';
        }
        if (str_starts_with($signature, "RIFF")) {
            return 'image/webp';
        }
        return 'image/jpeg';
    }

    /**
     * Extrait le JSON propre depuis la réponse Claude (parfois entouré de markdown).
     */
    protected function parseJsonFromText(string $text, Trade $trade): ?array
    {
        // Strip markdown ```json fences si présents
        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
        $decoded = json_decode($clean, true);

        if (! is_array($decoded)) {
            Log::warning('[ai_quote] failed to parse Claude JSON', ['text' => mb_substr($text, 0, 300)]);
            return null;
        }

        if (isset($decoded['error'])) {
            return [
                'success' => false,
                'error' => $decoded['error'],
                'reason' => $decoded['reason'] ?? 'unknown',
                'trade_code' => $trade->code,
            ];
        }

        return [
            'success' => true,
            'trade_code' => $trade->code,
            'trade_name' => $trade->name,
            'surface_estimee_m2' => $decoded['surface_estimee_m2'] ?? null,
            'etat_observe' => $decoded['etat_observe'] ?? '',
            'duree_estimee_min' => (int) ($decoded['duree_estimee_min'] ?? 0),
            'prix_min_cents' => (int) ($decoded['prix_min_cents'] ?? 0),
            'prix_max_cents' => (int) ($decoded['prix_max_cents'] ?? 0),
            'prix_min_eur' => round(((int) ($decoded['prix_min_cents'] ?? 0)) / 100, 2),
            'prix_max_eur' => round(((int) ($decoded['prix_max_cents'] ?? 0)) / 100, 2),
            'confiance' => (int) ($decoded['confiance'] ?? 0),
            'observations' => $decoded['observations'] ?? '',
            'options_suggerees' => $decoded['options_suggerees'] ?? [],
        ];
    }
}
