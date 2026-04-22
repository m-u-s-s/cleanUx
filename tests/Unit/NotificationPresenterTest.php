<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\FinanceReminderNotification;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presenter_resolves_type_label_and_action_url_from_payload(): void
    {
        $user = User::factory()->admin()->create();

        $notification = new DatabaseNotification([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => FinanceReminderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'finance',
                'title' => 'Facture en retard',
                'message' => 'Merci de régulariser la facture INV-001.',
                'action_url' => route('admin.finance'),
                'invoice_number' => 'INV-001',
            ],
        ]);

        $presenter = app(NotificationPresenter::class);

        $this->assertSame('finance', $presenter->typeKey($notification));
        $this->assertSame('Finance', $presenter->label($notification));
        $this->assertSame(route('admin.finance'), $presenter->actionUrl($notification, $user));
        $this->assertStringContainsString('inv-001', $presenter->searchableText($notification));
    }
}
