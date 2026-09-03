<?php

namespace Database\Factories;

use App\Models\EmailSendRule;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailSendRule> */
class EmailSendRuleFactory extends Factory
{
    protected $model = EmailSendRule::class;

    public function definition(): array
    {
        return [
            'email_template_id' => EmailTemplate::factory(),
            'name' => $this->faker->words(3, true),
            'is_active' => true,
            'trigger_type' => 'manual',
            'offset_minutes' => 0,
            'hour' => 9,
            'cap_per_recipient' => 0,
            'cap_window_hours' => 24,
            'respects_opt_out' => true,
        ];
    }

    /** Une règle branchée sur un événement du moteur d'automatisation. */
    public function surEvenement(string $cle): static
    {
        return $this->state(fn () => ['trigger_type' => 'event', 'trigger_key' => $cle]);
    }

    /** Un rappel calé sur un jalon : minutes négatives = avant. */
    public function rappel(string $jalon, int $minutes): static
    {
        return $this->state(fn () => [
            'trigger_type' => 'reminder',
            'trigger_key' => $jalon,
            'offset_minutes' => $minutes,
        ]);
    }

    /** Un plafond : n envois par destinataire sur la fenêtre. */
    public function plafond(int $nombre, int $fenetreHeures = 24): static
    {
        return $this->state(fn () => [
            'cap_per_recipient' => $nombre,
            'cap_window_hours' => $fenetreHeures,
        ]);
    }
}
