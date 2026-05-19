<?php

namespace App\Services\Push\Providers;

use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Provider Push mock — log au lieu d'envoyer réellement.
 *
 * Comportement spécial :
 *   - token contenant "invalid" → token_invalid (signale au service d'invalider)
 *   - token contenant "fail" → failed
 *   - sinon → accepted
 */
class PushMockProvider implements PushProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function supportsPlatforms(): array
    {
        return ['ios', 'android', 'web'];
    }

    public function send(PushSendRequest $request): PushSendResult
    {
        Log::info('PushMockProvider::send', [
            'platform' => $request->platform,
            'token_prefix' => substr($request->token, 0, 12) . '...',
            'title' => $request->title,
            'category' => $request->category,
            'idempotency_key' => $request->idempotencyKey,
        ]);

        $lower = strtolower($request->token);

        if (str_contains($lower, 'invalid')) {
            return PushSendResult::failed(
                'Mock token invalidated',
                'mock_invalid_token',
                tokenInvalid: true,
            );
        }

        if (str_contains($lower, 'fail')) {
            return PushSendResult::failed(
                'Mock auto-fail',
                'mock_fail',
            );
        }

        return PushSendResult::accepted(
            'mock_push_' . Str::lower(Str::random(16)),
            'sent',
            ['simulated' => true],
        );
    }
}
