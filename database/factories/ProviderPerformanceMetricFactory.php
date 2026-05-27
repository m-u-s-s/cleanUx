<?php

namespace Database\Factories;

use App\Models\ProviderPerformanceMetric;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderPerformanceMetricFactory extends Factory
{
    protected $model = ProviderPerformanceMetric::class;

    public function definition(): array
    {
        $received  = fake()->numberBetween(20, 200);
        $accepted  = (int) round($received * fake()->randomFloat(2, 0.5, 0.95));
        $declined  = $received - $accepted - fake()->numberBetween(0, 5);
        $expired   = $received - $accepted - max(0, $declined);
        $completed = (int) round($accepted * fake()->randomFloat(2, 0.8, 1.0));
        $cancelled = $accepted - $completed;

        $acceptanceRate  = $received > 0 ? round($accepted / $received, 4) : 0;
        $completionRate  = $accepted > 0 ? round($completed / $accepted, 4) : 0;
        $cancellationRate = $accepted > 0 ? round($cancelled / $accepted, 4) : 0;

        $start = now()->subDays(30)->startOfDay();
        $end   = now()->endOfDay();

        return [
            'user_id'                      => User::factory(),
            'period_start'                 => $start->toDateString(),
            'period_end'                   => $end->toDateString(),
            'window_days'                  => 30,
            'offers_received'              => $received,
            'offers_accepted'              => $accepted,
            'offers_declined'              => max(0, $declined),
            'offers_expired'               => max(0, $expired),
            'missions_completed'           => $completed,
            'missions_cancelled_by_provider' => max(0, $cancelled),
            'acceptance_rate'              => $acceptanceRate,
            'completion_rate'              => $completionRate,
            'cancellation_rate'            => $cancellationRate,
            'avg_response_seconds'         => fake()->numberBetween(30, 600),
            'rating_avg_window'            => number_format(fake()->randomFloat(1, 3.5, 5.0), 2),
            'rating_count_window'          => fake()->numberBetween(5, 50),
            'computed_at'                  => now(),
        ];
    }
}
