<?php

namespace App\Services\Email;

use Illuminate\Notifications\Messages\MailMessage;

class ProductMailMessageFactory
{
    public static function make(array $payload): MailMessage
    {
        return (new MailMessage)
            ->subject((string) ($payload['subject'] ?? 'Notification CleanUx'))
            ->view('emails.product.generic', [
                'eyebrow' => $payload['eyebrow'] ?? 'CleanUx',
                'title' => $payload['title'] ?? ($payload['subject'] ?? 'Notification'),
                'intro' => $payload['intro'] ?? null,
                'details' => array_values(array_filter((array) ($payload['details'] ?? []), static fn ($item) => filled($item['label'] ?? null))),
                'highlight' => $payload['highlight'] ?? null,
                'actionText' => $payload['action_text'] ?? null,
                'actionUrl' => $payload['action_url'] ?? null,
                'outro' => $payload['outro'] ?? null,
                'tone' => $payload['tone'] ?? 'info',
                'footer' => $payload['footer'] ?? 'CleanUx — plateforme de gestion des interventions.',
            ]);
    }
}
