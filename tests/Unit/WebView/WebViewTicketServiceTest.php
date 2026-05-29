<?php

namespace Tests\Unit\WebView;

use App\Models\User;
use App\Services\WebView\WebViewTicketService;
use Tests\TestCase;

class WebViewTicketServiceTest extends TestCase
{
    private function service(): WebViewTicketService
    {
        return app(WebViewTicketService::class);
    }

    public function test_issue_then_redeem_returns_bound_payload(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $svc = $this->service();

        $ticket = $svc->issue($user, 'device-abc', '/dashboard');
        $payload = $svc->redeem($ticket);

        $this->assertNotNull($payload);
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame('device-abc', $payload['device_id']);
        $this->assertSame('/dashboard', $payload['target_path']);
        $this->assertNull($payload['token_id']); // no Sanctum token bound in unit context
    }

    public function test_ticket_is_single_use(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $svc = $this->service();

        $ticket = $svc->issue($user, 'd', '/x');
        $this->assertNotNull($svc->redeem($ticket));
        $this->assertNull($svc->redeem($ticket)); // second redeem fails
    }

    public function test_unknown_ticket_returns_null(): void
    {
        $this->assertNull($this->service()->redeem('not-a-real-ticket'));
    }
}
