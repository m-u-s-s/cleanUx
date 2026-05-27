<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'code'                => fake()->unique()->slug(2),
            'name'                => fake()->words(3, true),
            'description'         => fake()->sentence(),
            'category'            => 'transactional',
            'subject_pattern'     => fake()->sentence(),
            'body_html_pattern'   => '<p>{{ greeting }},</p><p>' . fake()->paragraph() . '</p>',
            'body_text_pattern'   => fake()->paragraph(),
            'locale_overrides'    => null,
            'required_variables'  => ['greeting', 'name'],
            'is_active'           => true,
            'metadata'            => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
