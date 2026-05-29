<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Sprint 0 — Task 2 : JSON exception handler unifié.
 *
 * Vérifie que toutes les erreurs API retournent le shape unifié :
 *   { ok: false, error_code, message, errors? }
 */
class ExceptionHandlerJsonTest extends TestCase
{
    use RefreshDatabase;

    private bool $origDebug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origDebug = (bool) config('app.debug');
    }

    protected function tearDown(): void
    {
        config(['app.debug' => $this->origDebug]);
        parent::tearDown();
    }

    // ---------------------------------------------------------------------------
    // 1. Validation error → 422
    // ---------------------------------------------------------------------------

    public function test_validation_error_returns_unified_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // POST /api/client/bookings with empty body triggers required-field validation
        $r = $this->postJson('/api/client/bookings', []);

        $r->assertStatus(422)
            ->assertJson(['ok' => false, 'error_code' => 'validation_failed'])
            ->assertJsonStructure(['ok', 'error_code', 'message', 'errors']);
    }

    // ---------------------------------------------------------------------------
    // 2. Unauthenticated → 401
    // ---------------------------------------------------------------------------

    public function test_unauthenticated_returns_unified_shape(): void
    {
        $r = $this->getJson('/api/auth/me');

        $r->assertStatus(401)
            ->assertJson(['ok' => false, 'error_code' => 'unauthenticated'])
            ->assertJsonStructure(['ok', 'error_code', 'message']);
    }

    // ---------------------------------------------------------------------------
    // 3. Not found (ModelNotFoundException via route model binding) → 404
    // ---------------------------------------------------------------------------

    public function test_not_found_returns_unified_shape(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $r = $this->getJson('/api/client/bookings/99999999');

        $r->assertStatus(404)
            ->assertJson(['ok' => false, 'error_code' => 'not_found']);
    }

    // ---------------------------------------------------------------------------
    // 4. Rate limited → 429
    //    throttle:auth limiter = Limit::perMinute(10)->by($request->ip())
    //    ThrottleRequests stores the key as md5($limiterName . $limit->key)
    //    i.e. md5('auth' . '127.0.0.1') when shouldHashKeys = true (default).
    //    We pre-fill via RateLimiter::hit() with the correct hashed key.
    // ---------------------------------------------------------------------------

    public function test_throttled_returns_unified_shape(): void
    {
        // Exhaust the throttle:auth limiter (10/min) via real HTTP requests so the
        // test is immune to any Laravel hashing-scheme changes (md5 → sha1 etc.).
        // Bad credentials return 401 quickly (no DB heavy path), so 11 loops are fast.
        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@x.x', 'password' => 'x']);
        }

        // Guard: confirm the limiter is actually full before the assertion request.
        // If Laravel ever changes its internal key format this assertion will fail with
        // a clear message instead of a silent false-positive.
        $hashedKey = md5('auth'.'127.0.0.1');
        $this->assertTrue(
            RateLimiter::tooManyAttempts($hashedKey, 10),
            'Limiter was not actually full — throttle test setup is broken (hashing scheme may have changed)'
        );

        $r = $this->postJson('/api/auth/login', ['email' => 'x@x.x', 'password' => 'x']);

        $r->assertStatus(429)
            ->assertJson(['ok' => false, 'error_code' => 'rate_limited']);
    }

    // ---------------------------------------------------------------------------
    // 5. Server error in production (debug=false) → 500, no exception details leaked
    // ---------------------------------------------------------------------------

    public function test_server_error_returns_unified_shape_in_production(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/test-boom', function () {
            throw new \RuntimeException('boom');
        });

        $r = $this->getJson('/api/test-boom');

        $r->assertStatus(500)
            ->assertJson(['ok' => false, 'error_code' => 'server_error']);

        $this->assertStringNotContainsString('boom', $r->getContent());
        $this->assertArrayNotHasKey('debug', $r->json());
    }

    // ---------------------------------------------------------------------------
    // 6. AccessDeniedHttpException → 403 with error_code 'forbidden'
    // ---------------------------------------------------------------------------

    public function test_access_denied_returns_forbidden_error_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Register a test route that throws AccessDeniedHttpException
        Route::middleware('auth:sanctum')
            ->get('/api/test-forbidden', fn () => throw new AccessDeniedHttpException('No access'));

        $r = $this->getJson('/api/test-forbidden');

        $r->assertStatus(403)
            ->assertJson(['ok' => false, 'error_code' => 'forbidden']);
    }

    // ---------------------------------------------------------------------------
    // 7. Non-API HTML request keeps default Laravel behavior (HTML page, not JSON)
    // ---------------------------------------------------------------------------

    public function test_html_request_keeps_default_behavior(): void
    {
        $r = $this->get('/non-existent-route-for-exception-handler-test');

        $r->assertStatus(404);
        $this->assertStringContainsString('<', $r->getContent()); // HTML
    }
}
