<?php

namespace App\Support\Notifications;

trait InteractsWithUserNotificationPreferences
{
    /**
     * @param  array<int, string>  $defaults
     * @return array<int, string>
     */
    protected function preferredChannels(object $notifiable, string $eventKey, array $defaults): array
    {
        $channels = [];

        foreach ($defaults as $channel) {
            if (! method_exists($notifiable, 'wantsNotificationChannel') || $notifiable->wantsNotificationChannel($eventKey, $channel)) {
                $channels[] = $channel;
            }
        }

        if ($channels === [] && in_array('database', $defaults, true)) {
            return ['database'];
        }

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function basePayload(array $payload): array
    {
        return array_filter($payload, static fn ($value) => $value !== null && $value !== '');
    }
}
