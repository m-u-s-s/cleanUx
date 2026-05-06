<?php

namespace Tests\Feature\Assistant;

use App\Services\Assistant\Llm\AnthropicProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicProviderTest extends TestCase
{
    public function test_returns_error_when_api_key_missing(): void
    {
        config(['services.anthropic.key' => null]);

        $provider = app(AnthropicProvider::class);
        $response = $provider->chat('test system', [['role' => 'user', 'content' => 'hi']]);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $response->error);
    }

    public function test_parses_text_response(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'id'      => 'msg_test',
                'role'    => 'assistant',
                'model'   => 'claude-sonnet-4-20250514',
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'text', 'text' => 'Bonjour, je peux t\'aider.'],
                ],
                'usage'   => ['input_tokens' => 10, 'output_tokens' => 8],
            ], 200),
        ]);

        $provider = app(AnthropicProvider::class);
        $response = $provider->chat('system', [['role' => 'user', 'content' => 'salut']]);

        $this->assertFalse($response->isError());
        $this->assertSame('end_turn', $response->stopReason);
        $this->assertSame('Bonjour, je peux t\'aider.', $response->text);
        $this->assertFalse($response->hasToolUses());
        $this->assertSame(10, $response->usage['input_tokens'] ?? null);
    }

    public function test_parses_tool_use_response(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'tool_use',
                'content' => [
                    ['type' => 'text', 'text' => 'Je vais consulter tes réservations.'],
                    [
                        'type'  => 'tool_use',
                        'id'    => 'toolu_abc',
                        'name'  => 'list_my_bookings',
                        'input' => ['status' => 'pending', 'limit' => 5],
                    ],
                ],
                'usage' => ['input_tokens' => 50, 'output_tokens' => 20],
            ], 200),
        ]);

        $provider = app(AnthropicProvider::class);
        $response = $provider->chat('sys', [['role' => 'user', 'content' => 'mes resa ?']]);

        $this->assertTrue($response->hasToolUses());
        $this->assertCount(1, $response->toolUses);
        $this->assertSame('list_my_bookings', $response->toolUses[0]['name']);
        $this->assertSame('pending', $response->toolUses[0]['input']['status']);
    }

    public function test_handles_api_error_response(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'error' => ['type' => 'invalid_request_error', 'message' => 'Bad model name'],
            ], 400),
        ]);

        $provider = app(AnthropicProvider::class);
        $response = $provider->chat('sys', [['role' => 'user', 'content' => 'x']]);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Bad model name', $response->error);
    }

    public function test_passes_tools_in_payload(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content'     => [['type' => 'text', 'text' => 'ok']],
            ], 200),
        ]);

        $provider = app(AnthropicProvider::class);
        $tools = [
            [
                'name'         => 'list_x',
                'description'  => 'lists',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass(), 'required' => []],
            ],
        ];

        $provider->chat('sys', [['role' => 'user', 'content' => 'go']], $tools);

        Http::assertSent(function ($req) {
            $body = $req->data();
            return ! empty($body['tools'])
                && $body['tools'][0]['name'] === 'list_x';
        });
    }
}
