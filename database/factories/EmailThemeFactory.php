<?php

namespace Database\Factories;

use App\Models\EmailTheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailTheme> */
class EmailThemeFactory extends Factory
{
    protected $model = EmailTheme::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'is_default' => false,
            'is_active' => true,
            'priority' => 0,
            'recurs_yearly' => false,
            'color_accent' => '#ffb648',
            'color_accent_contrast' => '#0f172a',
            'color_page_background' => '#f8fafc',
            'color_card_background' => '#ffffff',
            'color_text' => '#0f172a',
            'color_text_muted' => '#475569',
            'color_border' => '#e2e8f0',
            'color_banner_from' => '#0f172a',
            'color_banner_to' => '#1e293b',
            'font_stack' => 'Arial, Helvetica, sans-serif',
            'corner_radius' => 20,
            'footer_text' => 'Brio',
        ];
    }

    /** Le thème permanent de la maison. */
    public function parDefaut(): static
    {
        return $this->state(fn () => ['is_default' => true, 'starts_on' => null, 'ends_on' => null]);
    }

    /** Un thème saisonnier, borné par ses deux dates. */
    public function saison(string $debut, string $fin, int $priorite = 10, bool $annuel = false): static
    {
        return $this->state(fn () => [
            'is_default' => false,
            'starts_on' => $debut,
            'ends_on' => $fin,
            'priority' => $priorite,
            'recurs_yearly' => $annuel,
        ]);
    }
}
