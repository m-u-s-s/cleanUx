<?php

namespace App\Services\WebhooksV2;

class WebhookSigner
{
    public function sign(string $body, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $signedPayload = $timestamp . '.' . $body;
        $hmac = hash_hmac(
            (string) config('webhooks_v2.signature_algo', 'sha256'),
            $signedPayload,
            $secret
        );
        $version = (string) config('webhooks_v2.signature_version', 'v1');

        return "t={$timestamp}," . $version . "={$hmac}";
    }

    public function verify(string $body, string $signatureHeader, string $secret, ?int $now = null): bool
    {
        $now ??= time();
        $tolerance = (int) config('webhooks_v2.signature_tolerance_seconds', 300);
        $algo = (string) config('webhooks_v2.signature_algo', 'sha256');
        $version = (string) config('webhooks_v2.signature_version', 'v1');

        $parts = $this->parseHeader($signatureHeader);
        if (! isset($parts['t']) || ! isset($parts[$version])) {
            return false;
        }
        $t = (int) $parts['t'];
        if (abs($now - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac($algo, $t . '.' . $body, $secret);
        return hash_equals($expected, (string) $parts[$version]);
    }

    private function parseHeader(string $header): array
    {
        $out = [];
        foreach (explode(',', $header) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $segment, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }
}
