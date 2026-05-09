<?php

namespace Tests\Unit;

use App\Listeners\LogNotificationMailFailed;
use App\Listeners\LogNotificationMailSent;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\RappelRendezVousNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Tests\TestCase;

class EmailLogListenersTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_notification_sent_and_failed_are_logged(): void
    {
        $client = User::factory()->client()->create(['email' => 'client@example.test']);
        $rdv = Booking::factory()->create(['client_id' => $client->id]);
        $notification = new RappelRendezVousNotification($rdv, '24h');

        /** @var LogNotificationMailSent $sentListener */
        $sentListener = app(LogNotificationMailSent::class);

        /** @var LogNotificationMailFailed $failedListener */
        $failedListener = app(LogNotificationMailFailed::class);

        $sentListener->handle(new NotificationSent($client, $notification, 'mail', 'preview-id'));
        $failedListener->handle(new NotificationFailed($client, $notification, 'mail', ['error' => 'smtp down']));

        $this->assertDatabaseHas('email_logs', [
            'status' => 'sent',
            'recipient_email' => 'client@example.test',
            'channel' => 'mail',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'status' => 'failed',
            'recipient_email' => 'client@example.test',
            'channel' => 'mail',
        ]);
    }
}
