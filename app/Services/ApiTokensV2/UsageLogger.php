<?php

namespace App\Services\ApiTokensV2;

use App\Models\ApiTokenUsage;
use App\Models\Sanctum\PersonalAccessTokenV2;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UsageLogger
{
    public function shouldLog(Request $request): bool
    {
        if (! (bool) config('api_tokens_v2.audit_enabled', true)) {
            return false;
        }
        $excluded = (array) config('api_tokens_v2.audit_excluded_paths', []);
        $path = ltrim($request->path(), '/');
        foreach ($excluded as $ex) {
            if (str_starts_with($path, ltrim($ex, '/'))) {
                return false;
            }
        }
        $sample = (float) config('api_tokens_v2.audit_sample_rate', 1.0);
        if ($sample >= 1.0) {
            return true;
        }
        if ($sample <= 0.0) {
            return false;
        }
        return (mt_rand(1, 10000) / 10000) <= $sample;
    }

    public function record(
        Request $request,
        SymfonyResponse $response,
        PersonalAccessTokenV2 $token,
        ?int $latencyMs = null,
    ): ?ApiTokenUsage {
        try {
            $row = ApiTokenUsage::query()->create([
                'token_id' => $token->id,
                'route_path' => mb_substr('/' . ltrim($request->path(), '/'), 0, 191),
                'method' => $request->method(),
                'response_status' => $response->getStatusCode(),
                'latency_ms' => $latencyMs,
                'response_size_bytes' => $this->responseSize($response),
                'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
                'user_agent_short' => mb_substr((string) $request->userAgent(), 0, 191),
                'occurred_at' => now(),
            ]);
            $token->forceFill([
                'last_used_at' => now(),
                'last_used_ip_hash' => $row->ip_hash,
                'usage_count' => $token->usage_count + 1,
            ])->save();
            return $row;
        } catch (\Throwable $e) {
            Log::warning('[api_tokens_v2] usage log error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function responseSize(SymfonyResponse $response): ?int
    {
        try {
            if ($response instanceof Response) {
                $content = $response->getContent();
                return $content === false ? null : strlen($content);
            }
            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
