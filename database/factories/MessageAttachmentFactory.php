<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageAttachmentFactory extends Factory
{
    protected $model = MessageAttachment::class;

    public function definition(): array
    {
        return [
            'message_id'     => fn () => Message::factory()->create()->id,
            'uploaded_by'    => fn () => User::factory()->create()->id,
            'disk'           => 'public',
            'path'           => 'attachments/' . fake()->uuid() . '.pdf',
            'original_name'  => fake()->slug(2) . '.pdf',
            'mime_type'      => 'application/pdf',
            'size_bytes'     => fake()->numberBetween(10000, 5000000),
            'image_width'    => null,
            'image_height'   => null,
            'thumbnail_path' => null,
            'av_status'      => MessageAttachment::AV_STATUS_CLEAN,
            'av_engine'      => null,
            'av_scanned_at'  => null,
            'av_details'     => null,
            'metadata'       => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['av_status' => MessageAttachment::AV_STATUS_PENDING]);
    }
}
