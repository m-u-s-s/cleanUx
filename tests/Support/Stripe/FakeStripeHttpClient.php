<?php

namespace Tests\Support\Stripe;

use Stripe\HttpClient\ClientInterface;

/**
 * Test double for the Stripe SDK HTTP boundary. Register via
 * \Stripe\ApiRequestor::setHttpClient($fake) in a test's setUp; all static
 * Stripe calls are then served from canned JSON instead of the network.
 * No production code is touched.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<string, array{0:array,1:int}> keyed by "METHOD path" */
    private array $stubs = [];

    /** @var string[] */
    private array $calls = [];

    private array $lastKnown = [];

    public function stub(string $method, string $path, array $body, int $code = 200): self
    {
        $this->stubs[strtoupper($method).' '.$path] = [$body, $code];

        return $this;
    }

    /** @return string[] */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * Implements Stripe\HttpClient\ClientInterface::request.
     *
     * Signature verified via reflection against stripe-php installed in this
     * project: 7 params ($method, $absUrl, $headers, $params, $hasFile,
     * $apiMode = 'v1', $maxNetworkRetries = null).
     * No requestStreaming() method is declared in this interface version.
     *
     * @param  'delete'|'get'|'post'  $method
     * @param  'v1'|'v2'  $apiMode
     * @return array{0:string,1:int,2:array}
     */
    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url((string) $absUrl, PHP_URL_PATH);

        // Stripe absUrls are like https://api.stripe.com/v1/payment_intents
        // parse_url already returns the path portion including /v1/…
        if (! str_starts_with($path, '/v1') && ! str_starts_with($path, '/v2')) {
            $path = '/v1/'.ltrim($path, '/');
        }

        $key = strtoupper((string) $method).' '.$path;
        $this->calls[] = $key;

        if (isset($this->stubs[$key])) {
            [$body, $code] = $this->stubs[$key];
            $this->rememberLastKnown($path, $body);

            return [json_encode($body), $code, []];
        }

        if (strtoupper((string) $method) === 'GET' && isset($this->lastKnown[$path])) {
            return [json_encode($this->lastKnown[$path]), 200, []];
        }

        throw new \RuntimeException("FakeStripeHttpClient: no stub for {$key}. Registered stubs: ".implode(', ', array_keys($this->stubs)));
    }

    private function rememberLastKnown(string $path, array $body): void
    {
        $this->lastKnown[$path] = $body;

        if (! isset($body['id'])) {
            return;
        }

        // After a sub-action (capture/cancel/confirm), also store under the
        // resource path so a subsequent GET /v1/payment_intents/{id} resolves.
        $resourcePath = (string) preg_replace('#/(capture|cancel|confirm)$#', '', $path);
        if ($resourcePath !== $path) {
            $this->lastKnown[$resourcePath] = $body;
        }

        // After a POST to a collection endpoint (e.g. /v1/payment_intents) that
        // returns a resource with an id, also store under the individual resource
        // path (/v1/payment_intents/pi_xxx) so a subsequent retrieve() works.
        $individualPath = rtrim($path, '/').'/'.$body['id'];
        if ($individualPath !== $path) {
            $this->lastKnown[$individualPath] = $body;
        }
    }
}
