<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\VerifyTurnstileCaptcha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VerifyTurnstileCaptchaCoverageBatch17Test extends TestCase
{
    private function buildRequest(array $body = [], array $headers = []): Request
    {
        $request = Request::create('/turnstile-probe', 'POST', $body);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return $request;
    }

    private function passThrough(): \Closure
    {
        return function (Request $request) {
            return new Response('passed', 200);
        };
    }

    public function test_soft_bypass_when_secret_missing_in_non_production(): void
    {
        config(['services.turnstile.secret_key' => '']);
        $this->app['env'] = 'testing';

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle($this->buildRequest(), $this->passThrough());

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('passed', $result->getContent());
    }

    public function test_misconfigured_returns_503_in_production_without_secret(): void
    {
        config(['services.turnstile.secret_key' => '']);
        $this->app['env'] = 'production';

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle($this->buildRequest(), $this->passThrough());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(503, $result->getStatusCode());
        $this->assertSame('captcha_misconfigured', $result->getData(true)['error']);

        $this->app['env'] = 'testing';
    }

    public function test_missing_token_returns_400(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle($this->buildRequest(), $this->passThrough());

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(400, $result->getStatusCode());
        $this->assertSame('captcha_required', $result->getData(true)['error']);
    }

    public function test_valid_token_via_body_passes_through(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle(
            $this->buildRequest(['cf-turnstile-response' => 'good-token']),
            $this->passThrough()
        );

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('passed', $result->getContent());
    }

    public function test_valid_token_via_header_passes_through(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle(
            $this->buildRequest([], ['X-Turnstile-Token' => 'header-token']),
            $this->passThrough()
        );

        $this->assertSame(200, $result->getStatusCode());
    }

    public function test_invalid_token_returns_403_with_error_codes(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle(
            $this->buildRequest(['cf-turnstile-response' => 'bad-token']),
            $this->passThrough()
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(403, $result->getStatusCode());
        $data = $result->getData(true);
        $this->assertSame('captcha_invalid', $data['error']);
        $this->assertSame(['invalid-input-response'], $data['codes']);
    }

    public function test_network_failure_fails_open_in_non_production(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);
        $this->app['env'] = 'testing';
        Http::fake(function () {
            throw new \RuntimeException('network down');
        });

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle(
            $this->buildRequest(['cf-turnstile-response' => 'some-token']),
            $this->passThrough()
        );

        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('passed', $result->getContent());
    }

    public function test_network_failure_fails_closed_in_production(): void
    {
        config(['services.turnstile.secret_key' => 'secret-xyz']);
        $this->app['env'] = 'production';
        Http::fake(function () {
            throw new \RuntimeException('network down');
        });

        $middleware = new VerifyTurnstileCaptcha;
        $result = $middleware->handle(
            $this->buildRequest(['cf-turnstile-response' => 'some-token']),
            $this->passThrough()
        );

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(503, $result->getStatusCode());
        $this->assertSame('captcha_service_unavailable', $result->getData(true)['error']);

        $this->app['env'] = 'testing';
    }
}
