<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AssistantRateLimit;
use App\Models\AssistantApiLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AssistantRateLimitCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    private AssistantRateLimit $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AssistantRateLimit;
    }

    private function buildRequest(?User $user, bool $json): Request
    {
        $request = Request::create('/assistant/send', 'POST');
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        // limited() uses the global request() helper, so bind this request in the
        // container first; binding triggers auth rebinding which would otherwise
        // clobber the user resolver, so set the resolver afterwards.
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function next(): \Closure
    {
        return fn (Request $r): Response => new Response('passed', 200);
    }

    public function test_guest_request_passes_straight_through(): void
    {
        $request = $this->buildRequest(null, true);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passed', $response->getContent());
    }

    public function test_user_under_all_limits_passes(): void
    {
        Config::set('services.assistant.rate_per_hour', 30);
        Config::set('services.assistant.rate_per_day', 200);
        Config::set('services.assistant.cost_limit_usd_per_day', 1.0);

        $user = User::factory()->client()->create();
        AssistantApiLog::factory()->create(['user_id' => $user->id, 'cost_usd' => 0.01]);

        $request = $this->buildRequest($user, true);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_hourly_limit_returns_429_json(): void
    {
        Config::set('services.assistant.rate_per_hour', 2);
        Config::set('services.assistant.rate_per_day', 200);
        Config::set('services.assistant.cost_limit_usd_per_day', 100.0);

        $user = User::factory()->client()->create();
        AssistantApiLog::factory()->count(2)->create(['user_id' => $user->id, 'cost_usd' => 0.0]);

        $request = $this->buildRequest($user, true);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(429, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('rate_limit_exceeded', $payload['error']);
        $this->assertSame('hour', $payload['reason']);
    }

    public function test_daily_limit_returns_429_json(): void
    {
        Config::set('services.assistant.rate_per_hour', 1000);
        Config::set('services.assistant.rate_per_day', 3);
        Config::set('services.assistant.cost_limit_usd_per_day', 100.0);

        $user = User::factory()->client()->create();
        AssistantApiLog::factory()->count(3)->create(['user_id' => $user->id, 'cost_usd' => 0.0]);

        $request = $this->buildRequest($user, true);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(429, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('day', $payload['reason']);
    }

    public function test_cost_limit_returns_429_json(): void
    {
        Config::set('services.assistant.rate_per_hour', 1000);
        Config::set('services.assistant.rate_per_day', 1000);
        Config::set('services.assistant.cost_limit_usd_per_day', 1.0);

        $user = User::factory()->client()->create();
        AssistantApiLog::factory()->count(2)->create(['user_id' => $user->id, 'cost_usd' => 0.75]);

        $request = $this->buildRequest($user, true);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(429, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertSame('cost', $payload['reason']);
    }

    public function test_non_json_limit_falls_back_to_redirect(): void
    {
        Config::set('services.assistant.rate_per_hour', 1);
        Config::set('services.assistant.rate_per_day', 200);
        Config::set('services.assistant.cost_limit_usd_per_day', 100.0);

        $user = User::factory()->client()->create();
        AssistantApiLog::factory()->create(['user_id' => $user->id, 'cost_usd' => 0.0]);

        $request = $this->buildRequest($user, false);

        $response = $this->middleware->handle($request, $this->next());

        $this->assertSame(302, $response->getStatusCode());
    }
}
