<?php

namespace Tests\Feature\I18n\Fixtures;

use Illuminate\Notifications\Notification;

class TestLocalizedNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'message' => __('app.account'),
            'locale_at_render' => app()->getLocale(),
        ];
    }
}
