<?php

namespace Tests\Feature\Assistant;

use App\Models\AssistantApiLog;
use App\Models\AssistantConversation;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Assistant\Llm\LlmResponse;
use App\Services\Assistant\Logging\CostCalculator;
use App\Services\Assistant\Logging\LogRecorder;
use App\Services\Assistant\Tools\AssistantToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantPhase51Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // CostCalculator
    // ──────────────────────────────────────────────────────

    public function test_cost_calculator_handles_claude_sonnet_4(): void
    {
        $cost = app(CostCalculator::class)->compute('claude-sonnet-4-20250514', 1000, 500);

        // 1000 input  → 1000 / 1M * 3.00 = 0.003
        // 500  output → 500  / 1M * 15.00 = 0.0075
        // Total = 0.0105
        $this->assertEqualsWithDelta(0.0105, $cost, 0.0001);
    }

    public function test_cost_calculator_handles_haiku(): void
    {
        $cost = app(CostCalculator::class)->compute('claude-haiku-4-5', 10_000, 2_000);

        // 10000 input  → 10000 / 1M * 0.80 = 0.008
        // 2000  output → 2000  / 1M * 4.00 = 0.008
        // Total = 0.016
        $this->assertEqualsWithDelta(0.016, $cost, 0.0001);
    }

    public function test_cost_calculator_returns_null_for_unknown_model(): void
    {
        $cost = app(CostCalculator::class)->compute('llama-7b', 1000, 500);
        $this->assertNull($cost);
    }

    public function test_cost_calculator_returns_null_when_tokens_missing(): void
    {
        $cost = app(CostCalculator::class)->compute('claude-sonnet-4', null, 500);
        $this->assertNull($cost);
    }

    // ──────────────────────────────────────────────────────
    // LogRecorder
    // ──────────────────────────────────────────────────────

    public function test_log_recorder_persists_success_with_cost(): void
    {
        $user = User::factory()->create();
        $conv = AssistantConversation::create([
            'user_id'      => $user->id,
            'context_role' => $user->assistantContextRole()->value,
            'status'       => AssistantConversation::STATUS_OPEN,
        ]);

        $response = new LlmResponse(
            text: 'Hello world',
            stopReason: 'end_turn',
            toolUses: [],
            usage: ['input_tokens' => 100, 'output_tokens' => 50],
        );

        $log = app(LogRecorder::class)->recordSuccess(
            $user,
            $conv,
            'anthropic',
            'claude-sonnet-4-20250514',
            $response,
            850
        );

        $this->assertSame(AssistantApiLog::STATUS_SUCCESS, $log->status);
        $this->assertSame(100, $log->input_tokens);
        $this->assertSame(50,  $log->output_tokens);
        $this->assertSame(150, $log->total_tokens);
        $this->assertSame(850, $log->latency_ms);
        $this->assertNotNull($log->cost_usd);
    }

    public function test_log_recorder_persists_error(): void
    {
        $user = User::factory()->create();

        $log = app(LogRecorder::class)->recordError(
            $user,
            null,
            'anthropic',
            'claude-sonnet-4',
            'Connection timeout',
            5000,
            isTimeout: true,
        );

        $this->assertSame(AssistantApiLog::STATUS_TIMEOUT, $log->status);
        $this->assertStringContainsString('timeout', strtolower($log->error_message));
    }

    public function test_log_recorder_records_tool_use_count(): void
    {
        $user = User::factory()->create();

        $response = new LlmResponse(
            text: '',
            stopReason: 'tool_use',
            toolUses: [
                ['id' => 'toolu_1', 'name' => 'list_my_bookings', 'input' => []],
                ['id' => 'toolu_2', 'name' => 'create_booking',  'input' => []],
            ],
            usage: ['input_tokens' => 200, 'output_tokens' => 50],
        );

        $log = app(LogRecorder::class)->recordSuccess(
            $user, null, 'anthropic', 'claude-sonnet-4', $response, 1200
        );

        $this->assertSame(2, $log->tool_use_count);
        $this->assertContains('list_my_bookings', $log->tools_used);
        $this->assertContains('create_booking',  $log->tools_used);
    }

    // ──────────────────────────────────────────────────────
    // Tool Registry — nouveaux tools Phase 5.1
    // ──────────────────────────────────────────────────────

    public function test_registry_exposes_get_invoice_for_personal_client(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $names = array_map(
            fn ($t) => $t->name(),
            app(AssistantToolRegistry::class)->toolsForUser($user)
        );

        $this->assertContains('get_invoice', $names);
        $this->assertContains('report_issue', $names);
        $this->assertNotContains('register_site', $names); // pas pour particulier
    }

    public function test_registry_exposes_register_site_for_company_client(): void
    {
        $org  = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'role' => 'client',
            'organization_account_id' => $org->id,
        ]);

        $names = array_map(
            fn ($t) => $t->name(),
            app(AssistantToolRegistry::class)->toolsForUser($user)
        );

        // RegisterSiteTool nécessite la permission sites.create — peut donc être
        // filtré par authorize() même si whitelisé. On teste juste que le name
        // est bien dans la whitelist du registry.
        $allowed = (new \ReflectionClass(AssistantToolRegistry::class))
            ->getMethod('allowedToolNamesForRole');
        $allowed->setAccessible(true);
        $list = $allowed->invoke(new AssistantToolRegistry(), $user->assistantContextRole());

        $this->assertContains('register_site', $list);
    }

    public function test_registry_provides_get_invoice_tool_definition_in_anthropic_format(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $defs  = app(AssistantToolRegistry::class)->definitionsForUser($user);
        $names = array_column($defs, 'name');

        $this->assertContains('get_invoice', $names);

        $getInvoice = collect($defs)->firstWhere('name', 'get_invoice');
        $this->assertSame('object', $getInvoice['input_schema']['type']);
        $this->assertArrayHasKey('properties', $getInvoice['input_schema']);
    }
}
