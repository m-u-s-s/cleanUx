<?php

namespace App\Services\Notifications;

use Twilio\Rest\Client;

class SmsService
{
    public function send(?string $to, string $message): void
    {
        if (! $to) {
            return;
        }

        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $from = config('services.twilio.from');

        if (! $sid || ! $token || ! $from) {
            return;
        }

        $client = new Client($sid, $token);

        $client->messages->create($to, [
            'from' => $from,
            'body' => $message,
        ]);
    }
}