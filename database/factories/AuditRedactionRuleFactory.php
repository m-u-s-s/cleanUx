<?php

namespace Database\Factories;

use App\Models\AuditRedactionRule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AuditRedactionRuleFactory extends Factory
{
    protected $model = AuditRedactionRule::class;

    public function definition(): array
    {
        $matchType = fake()->randomElement([
            AuditRedactionRule::MATCH_KEY,
            AuditRedactionRule::MATCH_REGEX,
            AuditRedactionRule::MATCH_PATH,
        ]);

        $patterns = [
            AuditRedactionRule::MATCH_KEY   => 'password',
            AuditRedactionRule::MATCH_REGEX => '/\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}/',
            AuditRedactionRule::MATCH_PATH  => 'data.user.email',
        ];

        return [
            'code'         => 'rule_' . Str::lower(Str::random(8)),
            'name'         => fake()->words(3, true),
            'pattern'      => $patterns[$matchType],
            'match_type'   => $matchType,
            'replacement'  => '[REDACTED]',
            'scope_domain' => fake()->optional()->randomElement(['payments', 'users', 'kyc']),
            'is_active'    => true,
            'priority'     => fake()->numberBetween(1, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
