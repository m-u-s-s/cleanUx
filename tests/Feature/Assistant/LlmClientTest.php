<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantAction;
use App\Models\AssistantApiLog;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Assistant\Llm\LlmClient;
use App\Services\Assistant\Llm\LlmProvider;
use App\Services\Assistant\Llm\LlmResponse;
use App\Services\Assistant\Logging\LogRecorder;
use App\Services\Assistant\Tools\AssistantToolDispatcher;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use App\Services\AssistantContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmClientTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  LlmResponse[]  $responses
     */
    private function fakeProvider(array $responses): LlmProvider
    {
        return new class($responses) implements LlmProvider
        {
            public int $calls = 0;

            /** @param LlmResponse[] $responses */
            public function __construct(public array $responses) {}

            public function name(): string
            {
                return 'fake';
            }

            public function chat(string $systemPrompt, array $messages, array $tools = [], array $options = []): LlmResponse
            {
                $response = $this->responses[$this->calls] ?? end($this->responses);
                $this->calls++;

                return $response;
            }
        };
    }

    private function makeClient(LlmProvider $provider): LlmClient
    {
        return new LlmClient(
            $provider,
            app(AssistantContextBuilder::class),
            app(AssistantToolRegistry::class),
            app(AssistantToolDispatcher::class),
            app(LogRecorder::class),
        );
    }

    private function createConversation(User $user): AssistantConversation
    {
        return AssistantConversation::create([
            'user_id' => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status' => AssistantConversation::STATUS_OPEN,
        ]);
    }

    private function toolUseInput(): array
    {
        return [
            'scheduled_date' => '2026-07-15',
            'scheduled_time' => '10:00',
            'place_type' => 'apartment',
            'surface_m2' => 75,
            'address' => 'Rue des Chats 12',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'frequency' => 'unique',
        ];
    }

    public function test_simple_text_response_is_returned_and_persisted(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $provider = $this->fakeProvider([
            new LlmResponse(
                text: 'Bonjour, comment puis-je vous aider ?',
                stopReason: 'end_turn',
                usage: ['input_tokens' => 12, 'output_tokens' => 8],
            ),
        ]);

        $result = $this->makeClient($provider)->sendUserMessage($user, $conv, 'Salut');

        $this->assertSame('Bonjour, comment puis-je vous aider ?', $result['text']);
        $this->assertFalse($result['has_pending_action']);
        $this->assertNull($result['pending_action_id']);

        // user message + assistant response persisted
        $this->assertSame(1, AssistantMessage::where('sender_type', AssistantMessage::SENDER_USER)->count());
        $this->assertSame(1, AssistantMessage::where('sender_type', AssistantMessage::SENDER_ASSISTANT)->count());

        // a success log was recorded
        $this->assertSame(1, AssistantApiLog::where('status', AssistantApiLog::STATUS_SUCCESS)->count());
    }

    public function test_error_response_returns_friendly_message_and_logs_error(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $provider = $this->fakeProvider([
            LlmResponse::error('rate_limited'),
        ]);

        $result = $this->makeClient($provider)->sendUserMessage($user, $conv, 'Salut');

        $this->assertStringContainsString('rate_limited', $result['text']);
        $this->assertStringContainsString('problème', $result['text']);
        $this->assertFalse($result['has_pending_action']);
        $this->assertNull($result['pending_action_id']);

        // error path records an error log and persists no assistant message
        $this->assertSame(1, AssistantApiLog::where('status', AssistantApiLog::STATUS_ERROR)->count());
        $this->assertSame(0, AssistantMessage::where('sender_type', AssistantMessage::SENDER_ASSISTANT)->count());
    }

    public function test_tool_use_loop_dispatches_then_returns_final_text(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        $toolUse = new LlmResponse(
            text: 'Je prépare la réservation.',
            stopReason: 'tool_use',
            toolUses: [[
                'id' => 'toolu_booking_1',
                'name' => 'create_booking',
                'input' => $this->toolUseInput(),
            ]],
            usage: ['input_tokens' => 20, 'output_tokens' => 15],
        );

        $finalText = new LlmResponse(
            text: 'Votre réservation est en attente de confirmation.',
            stopReason: 'end_turn',
            usage: ['input_tokens' => 30, 'output_tokens' => 10],
        );

        // chat() is called twice per loop iteration: feed both turns.
        $provider = $this->fakeProvider([$toolUse, $toolUse, $finalText, $finalText]);

        $result = $this->makeClient($provider)->sendUserMessage($user, $conv, 'Réserve un ménage');

        $this->assertSame('Votre réservation est en attente de confirmation.', $result['text']);
        $this->assertTrue($result['has_pending_action']);
        $this->assertNotNull($result['pending_action_id']);

        // a pending confirmation action was created by the write tool
        $this->assertSame(1, AssistantAction::count());
        $this->assertSame(
            AssistantAction::STATUS_PENDING_CONFIRMATION,
            AssistantAction::first()->status,
        );

        // the tool_result was persisted in the conversation
        $this->assertSame(
            1,
            AssistantMessage::where('sender_type', AssistantMessage::SENDER_TOOL_RESULT)->count(),
        );
        $this->assertSame((int) $result['pending_action_id'], AssistantAction::first()->id);
    }

    public function test_loop_hitting_iteration_limit_returns_fallback_message(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $conv = $this->createConversation($user);

        // Provider always asks for a tool: the loop never reaches a final text.
        $toolUse = new LlmResponse(
            text: '',
            stopReason: 'tool_use',
            toolUses: [[
                'id' => 'toolu_loop',
                'name' => 'create_booking',
                'input' => $this->toolUseInput(),
            ]],
            usage: ['input_tokens' => 5, 'output_tokens' => 5],
        );

        $provider = $this->fakeProvider([$toolUse]);

        $result = $this->makeClient($provider)->sendUserMessage($user, $conv, 'Boucle infinie');

        $this->assertStringContainsString('limite de tours', $result['text']);
    }
}
