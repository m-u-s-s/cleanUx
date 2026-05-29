<?php

namespace Database\Factories;

use App\Models\AssistantApiLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantApiLogFactory extends Factory
{
    protected $model = AssistantApiLog::class;

    public function definition(): array
    {
        $inputTokens = fake()->numberBetween(100, 2000);
        $outputTokens = fake()->numberBetween(50, 1000);

        return [
            'user_id' => fn () => User::factory()->create()->id,
            'assistant_conversation_id' => null,
            'provider' => 'anthropic',
            'model' => 'claude-3-5-sonnet-20241022',
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'cost_usd' => round(($inputTokens * 0.000003) + ($outputTokens * 0.000015), 6),
            'latency_ms' => fake()->numberBetween(500, 8000),
            'status' => AssistantApiLog::STATUS_SUCCESS,
            'stop_reason' => 'end_turn',
            'error_message' => null,
            'tool_use_count' => 0,
            'tools_used' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AssistantApiLog::STATUS_ERROR,
            'error_message' => 'Rate limit exceeded',
            'output_tokens' => 0,
            'total_tokens' => 0,
        ]);
    }
}
