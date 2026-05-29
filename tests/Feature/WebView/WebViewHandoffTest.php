<?php

namespace Tests\Feature\WebView;

use App\Models\User;
use App\Services\WebView\WebViewTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebViewHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/auth/webview-ticket', ['target_path' => '/dashboard'])
            ->assertUnauthorized();
    }

    public function test_ticket_endpoint_returns_enter_url(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/auth/webview-ticket', [
            'target_path' => '/dashboard',
            'device_id' => 'device-1',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['url']);

        $this->assertStringContainsString('/m/enter?ticket=', $response->json('url'));
    }

    public function test_ticket_endpoint_rejects_external_path(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', ['target_path' => 'https://evil.example/phish'])
            ->assertStatus(422);

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '//evil.example'])
            ->assertStatus(422);
    }

    public function test_ticket_endpoint_rejects_backslash_and_encoded_redirects(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '/\\evil.example'])
            ->assertStatus(422);

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '/%2F%2Fevil.example'])
            ->assertStatus(422);
    }

    public function test_ticket_endpoint_works_without_device_id(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '/dashboard'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_enter_redeems_ticket_logs_in_and_redirects_to_embed(): void
    {
        $user = User::factory()->create();
        $ticket = app(WebViewTicketService::class)->issue($user, 'device-1', '/dashboard');

        $this->get('/m/enter?ticket='.$ticket)
            ->assertRedirect('/dashboard?embed=1');

        $this->assertAuthenticatedAs($user);
    }

    public function test_enter_appends_embed_param_when_path_already_has_query(): void
    {
        $user = User::factory()->create();
        $ticket = app(WebViewTicketService::class)->issue($user, 'd', '/orders?page=2');

        $this->get('/m/enter?ticket='.$ticket)
            ->assertRedirect('/orders?page=2&embed=1');
    }

    public function test_enter_with_used_ticket_returns_419(): void
    {
        $user = User::factory()->create();
        $ticket = app(WebViewTicketService::class)->issue($user, 'd', '/dashboard');

        $this->get('/m/enter?ticket='.$ticket)->assertRedirect();
        $this->get('/m/enter?ticket='.$ticket)->assertStatus(419);
    }

    public function test_enter_with_unknown_ticket_returns_419(): void
    {
        $this->get('/m/enter?ticket=garbage')->assertStatus(419);
    }
}
