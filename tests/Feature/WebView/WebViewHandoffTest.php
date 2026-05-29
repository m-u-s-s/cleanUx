<?php

namespace Tests\Feature\WebView;

use App\Models\User;
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

        $this->postJson('/api/auth/webview-ticket', [
            'target_path' => '/dashboard',
            'device_id' => 'device-1',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['url']);
    }

    public function test_ticket_endpoint_rejects_external_path(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/auth/webview-ticket', ['target_path' => 'https://evil.example/phish'])
            ->assertStatus(422);

        $this->postJson('/api/auth/webview-ticket', ['target_path' => '//evil.example'])
            ->assertStatus(422);
    }
}
