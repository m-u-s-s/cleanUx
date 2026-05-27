<?php

namespace Tests\Feature\Assistant;

use App\Models\User;
use App\Services\Assistant\Prompts\RolePromptBuilder;
use App\Services\AssistantContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // Delegation contract
    // ──────────────────────────────────────────────────────

    public function test_delegates_to_context_builder(): void
    {
        $user = User::factory()->create();

        $contextBuilder = $this->createMock(AssistantContextBuilder::class);
        $contextBuilder
            ->expects($this->once())
            ->method('build')
            ->with($user)
            ->willReturn(['system' => 'mocked system prompt', 'context' => [], 'tools' => []]);

        $builder = new RolePromptBuilder($contextBuilder);
        $prompt  = $builder->buildSystemPrompt($user);

        $this->assertSame('mocked system prompt', $prompt);
    }

    // ──────────────────────────────────────────────────────
    // Integration: returns non-empty string for any user
    // ──────────────────────────────────────────────────────

    public function test_returns_non_empty_string_for_default_user(): void
    {
        $user   = User::factory()->create();
        $prompt = app(RolePromptBuilder::class)->buildSystemPrompt($user);

        $this->assertIsString($prompt);
        $this->assertNotEmpty($prompt);
    }

    public function test_prompt_contains_cleanux_brand(): void
    {
        $user   = User::factory()->create();
        $prompt = app(RolePromptBuilder::class)->buildSystemPrompt($user);

        $this->assertStringContainsStringIgnoringCase('CleanUx', $prompt);
    }

    public function test_admin_prompt_differs_from_client_prompt(): void
    {
        $admin  = User::factory()->create(['platform_role' => 'admin']);
        $client = User::factory()->create();

        $adminPrompt  = app(RolePromptBuilder::class)->buildSystemPrompt($admin);
        $clientPrompt = app(RolePromptBuilder::class)->buildSystemPrompt($client);

        $this->assertNotSame($adminPrompt, $clientPrompt);
    }
}
