<?php

namespace Database\Factories;

use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    public function definition(): array
    {
        return [
            'code'               => EmailMessage::generateCode(),
            'provider'           => 'smtp',
            'provider_message_id' => null,
            'to_email'           => fake()->safeEmail(),
            'to_name'            => fake()->name(),
            'to_user_id'         => fn () => User::factory()->create()->id,
            'from_email'         => 'no-reply@cleanux.be',
            'from_name'          => 'CleanUx',
            'reply_to'           => null,
            'subject'            => fake()->sentence(),
            'body_html'          => '<p>' . fake()->paragraph() . '</p>',
            'body_text'          => fake()->paragraph(),
            'cc'                 => null,
            'bcc'                => null,
            'attachments'        => null,
            'headers'            => null,
            'category'           => EmailMessage::CATEGORY_TRANSACTIONAL,
            'template_code'      => null,
            'locale'             => 'fr',
            'status'             => EmailMessage::STATUS_SENT,
            'queued_at'          => now()->subMinutes(2),
            'sent_at'            => now(),
            'delivered_at'       => now()->addSeconds(5),
            'opened_at'          => null,
            'clicked_at'         => null,
            'bounced_at'         => null,
            'complained_at'      => null,
            'last_error'         => null,
            'attempts'           => 1,
            'idempotency_key'    => null,
            'metadata'           => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status'       => EmailMessage::STATUS_PENDING,
            'queued_at'    => null,
            'sent_at'      => null,
            'delivered_at' => null,
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status'     => EmailMessage::STATUS_BOUNCED,
            'bounced_at' => now(),
        ]);
    }
}
