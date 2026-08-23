<?php

namespace Tests\Feature\Assistant;

use App\Services\Assistant\Llm\AnthropicStreamingProvider;
use App\Services\Assistant\Streaming\StreamEvent;
use ReflectionMethod;
use Tests\TestCase;

/** Couverture de AnthropicStreamingProvider. */
class AnthropicStreamingProviderTest extends TestCase
{
    private function provider(): AnthropicStreamingProvider
    {
        return new AnthropicStreamingProvider;
    }

    private function parseSseFrame(string $raw): array
    {
        $m = new ReflectionMethod(AnthropicStreamingProvider::class, 'parseSseFrame');
        $m->setAccessible(true);

        return $m->invoke($this->provider(), $raw);
    }

    private function mapToStreamEvent(array $frame): ?StreamEvent
    {
        $m = new ReflectionMethod(AnthropicStreamingProvider::class, 'mapToStreamEvent');
        $m->setAccessible(true);

        return $m->invoke($this->provider(), $frame);
    }

    // ──────────────────────────────────────────────────────
    // Guard: clé API manquante
    // ──────────────────────────────────────────────────────

    public function test_emits_error_event_when_api_key_missing(): void
    {
        config(['services.anthropic.key' => null]);

        $events = [];
        $this->provider()->chatStream(
            'system',
            [['role' => 'user', 'content' => 'hi']],
            [],
            function (StreamEvent $event) use (&$events) {
                $events[] = $event;
            },
        );

        $this->assertCount(1, $events);
        $this->assertSame(StreamEvent::TYPE_ERROR, $events[0]->type);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $events[0]->payload['message']);
    }

    public function test_empty_string_api_key_is_treated_as_missing(): void
    {
        config(['services.anthropic.key' => '']);

        $events = [];
        $this->provider()->chatStream('s', [], [], function (StreamEvent $e) use (&$events) {
            $events[] = $e;
        });

        $this->assertCount(1, $events);
        $this->assertSame(StreamEvent::TYPE_ERROR, $events[0]->type);
    }

    // ──────────────────────────────────────────────────────
    // parseSseFrame
    // ──────────────────────────────────────────────────────

    public function test_parse_sse_frame_extracts_event_and_json_data(): void
    {
        $raw = "event: content_block_delta\ndata: {\"index\":0,\"delta\":{\"type\":\"text_delta\",\"text\":\"Hi\"}}";

        $frame = $this->parseSseFrame($raw);

        $this->assertSame('content_block_delta', $frame['event']);
        $this->assertSame(0, $frame['data']['index']);
        $this->assertSame('Hi', $frame['data']['delta']['text']);
    }

    public function test_parse_sse_frame_handles_crlf_line_endings(): void
    {
        $raw = "event: message_stop\r\ndata: {\"type\":\"message_stop\"}";

        $frame = $this->parseSseFrame($raw);

        $this->assertSame('message_stop', $frame['event']);
        $this->assertSame('message_stop', $frame['data']['type']);
    }

    public function test_parse_sse_frame_joins_multiple_data_lines(): void
    {
        $raw = "data: {\"a\":1,\ndata: \"b\":2}";

        $frame = $this->parseSseFrame($raw);

        $this->assertNull($frame['event']);
        $this->assertSame(1, $frame['data']['a']);
        $this->assertSame(2, $frame['data']['b']);
    }

    public function test_parse_sse_frame_without_data_returns_empty_array(): void
    {
        $frame = $this->parseSseFrame('event: ping');

        $this->assertSame('ping', $frame['event']);
        $this->assertSame([], $frame['data']);
    }

    public function test_parse_sse_frame_with_invalid_json_falls_back_to_empty(): void
    {
        $frame = $this->parseSseFrame('data: not-json');

        $this->assertSame([], $frame['data']);
    }

    // ──────────────────────────────────────────────────────
    // mapToStreamEvent — chaque branche du match
    // ──────────────────────────────────────────────────────

    public function test_maps_message_start_event(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'message_start',
            'data' => ['message' => ['model' => 'claude-x', 'usage' => ['input_tokens' => 42]]],
        ]);

        $this->assertSame(StreamEvent::TYPE_START, $event->type);
        $this->assertSame('claude-x', $event->payload['model']);
        $this->assertSame(42, $event->payload['input_tokens']);
    }

    public function test_maps_content_block_start_text(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_start',
            'data' => ['index' => 3, 'content_block' => ['type' => 'text']],
        ]);

        $this->assertSame(StreamEvent::TYPE_TEXT_BLOCK_START, $event->type);
        $this->assertSame(3, $event->payload['index']);
    }

    public function test_maps_content_block_start_tool_use(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_start',
            'data' => [
                'index' => 1,
                'content_block' => ['type' => 'tool_use', 'id' => 'toolu_x', 'name' => 'list_bookings'],
            ],
        ]);

        $this->assertSame(StreamEvent::TYPE_TOOL_USE_START, $event->type);
        $this->assertSame('toolu_x', $event->payload['tool_use_id']);
        $this->assertSame('list_bookings', $event->payload['tool_name']);
    }

    public function test_maps_content_block_start_unknown_type_to_null(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_start',
            'data' => ['index' => 0, 'content_block' => ['type' => 'image']],
        ]);

        $this->assertNull($event);
    }

    public function test_maps_content_block_delta_text_delta(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_delta',
            'data' => ['index' => 2, 'delta' => ['type' => 'text_delta', 'text' => 'World']],
        ]);

        $this->assertSame(StreamEvent::TYPE_TEXT_DELTA, $event->type);
        $this->assertSame(2, $event->payload['index']);
        $this->assertSame('World', $event->payload['text']);
    }

    public function test_maps_content_block_delta_input_json_delta(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_delta',
            'data' => ['index' => 0, 'delta' => ['type' => 'input_json_delta', 'partial_json' => '{"a":']],
        ]);

        $this->assertSame(StreamEvent::TYPE_TOOL_INPUT_DELTA, $event->type);
        $this->assertSame('{"a":', $event->payload['json_chunk']);
    }

    public function test_maps_content_block_delta_unknown_type_to_null(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_delta',
            'data' => ['index' => 0, 'delta' => ['type' => 'thinking_delta']],
        ]);

        $this->assertNull($event);
    }

    public function test_maps_content_block_stop_event(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'content_block_stop',
            'data' => ['index' => 5],
        ]);

        $this->assertSame(StreamEvent::TYPE_CONTENT_BLOCK_STOP, $event->type);
        $this->assertSame(5, $event->payload['index']);
    }

    public function test_maps_message_delta_event(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'message_delta',
            'data' => ['delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 17]],
        ]);

        $this->assertSame(StreamEvent::TYPE_MESSAGE_DELTA, $event->type);
        $this->assertSame('end_turn', $event->payload['stop_reason']);
        $this->assertSame(17, $event->payload['output_tokens']);
    }

    public function test_maps_message_stop_event(): void
    {
        $event = $this->mapToStreamEvent(['event' => 'message_stop', 'data' => []]);

        $this->assertSame(StreamEvent::TYPE_STOP, $event->type);
    }

    public function test_maps_ping_event_to_null(): void
    {
        $this->assertNull($this->mapToStreamEvent(['event' => 'ping', 'data' => []]));
    }

    public function test_maps_error_event(): void
    {
        $event = $this->mapToStreamEvent([
            'event' => 'error',
            'data' => ['error' => ['message' => 'overloaded']],
        ]);

        $this->assertSame(StreamEvent::TYPE_ERROR, $event->type);
        $this->assertSame('overloaded', $event->payload['message']);
    }

    public function test_maps_error_event_with_default_message(): void
    {
        $event = $this->mapToStreamEvent(['event' => 'error', 'data' => []]);

        $this->assertSame(StreamEvent::TYPE_ERROR, $event->type);
        $this->assertSame('Stream error', $event->payload['message']);
    }

    public function test_maps_unknown_event_to_null(): void
    {
        $this->assertNull($this->mapToStreamEvent(['event' => 'some_future_event', 'data' => []]));
    }

    public function test_resolves_type_from_data_when_event_missing(): void
    {
        // Pas de clé 'event' → on retombe sur data.type.
        $event = $this->mapToStreamEvent([
            'event' => null,
            'data' => ['type' => 'message_stop'],
        ]);

        $this->assertSame(StreamEvent::TYPE_STOP, $event->type);
    }
}
