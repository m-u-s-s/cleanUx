<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationPresenterCoverageBatch16Test extends TestCase
{
    use RefreshDatabase;

    private function makeNotification(array $data, string $type = 'App\\Notifications\\GenericNotification'): DatabaseNotification
    {
        return new DatabaseNotification([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => $data,
        ]);
    }

    public function test_type_key_resolves_each_class_and_payload_branch(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertSame('feedback', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\FeedbackReceivedNotification')));
        $this->assertSame('finance', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\FinanceReminderNotification')));
        $this->assertSame('urgent', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\UrgenceNotification')));
        $this->assertSame('urgent', $presenter->typeKey($this->makeNotification(['message' => 'Une URGENCE absolue'])));
        $this->assertSame('admin', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\AdminAlertNotification')));
        $this->assertSame('calendar', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\CalendarSyncNotification')));
        $this->assertSame('rendezvous', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\RappelNotification')));
        $this->assertSame('rendezvous', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\RdvNotification')));
        $this->assertSame('rendezvous', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\RendezVousNotification')));
        $this->assertSame('rendezvous', $presenter->typeKey($this->makeNotification(['rdv_id' => 42])));
        $this->assertSame('system', $presenter->typeKey($this->makeNotification([], 'App\\Notifications\\PlainNotification')));
        $this->assertSame('custom', $presenter->typeKey($this->makeNotification(['type' => 'custom'])));
    }

    public function test_label_maps_every_type_key(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertSame('Feedback', $presenter->label($this->makeNotification(['type' => 'feedback'])));
        $this->assertSame('Finance', $presenter->label($this->makeNotification(['type' => 'finance'])));
        $this->assertSame('Urgent', $presenter->label($this->makeNotification(['type' => 'urgent'])));
        $this->assertSame('Admin', $presenter->label($this->makeNotification(['type' => 'admin'])));
        $this->assertSame('Agenda', $presenter->label($this->makeNotification(['type' => 'calendar'])));
        $this->assertSame('Rendez-vous', $presenter->label($this->makeNotification(['type' => 'rendezvous'])));
        $this->assertSame('Système', $presenter->label($this->makeNotification(['type' => 'system'])));
    }

    public function test_severity_uses_payload_override_and_type_defaults(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertSame('success', $presenter->severity($this->makeNotification(['type' => 'system', 'severity' => 'success'])));
        $this->assertSame('danger', $presenter->severity($this->makeNotification(['type' => 'urgent'])));
        $this->assertSame('warning', $presenter->severity($this->makeNotification(['type' => 'finance'])));
        $this->assertSame('info', $presenter->severity($this->makeNotification(['type' => 'admin'])));
        $this->assertSame('info', $presenter->severity($this->makeNotification(['type' => 'calendar'])));
        $this->assertSame('default', $presenter->severity($this->makeNotification(['type' => 'system'])));
    }

    public function test_severity_classes_cover_each_variant_and_unread_flag(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertStringContainsString('emerald-50', $presenter->severityClasses($this->makeNotification(['type' => 'system', 'severity' => 'success']), true));
        $this->assertStringContainsString('emerald-100', $presenter->severityClasses($this->makeNotification(['type' => 'system', 'severity' => 'success']), false));
        $this->assertStringContainsString('amber-50', $presenter->severityClasses($this->makeNotification(['type' => 'finance']), true));
        $this->assertStringContainsString('red-50', $presenter->severityClasses($this->makeNotification(['type' => 'urgent']), true));
        $this->assertStringContainsString('blue-50', $presenter->severityClasses($this->makeNotification(['type' => 'admin']), true));
        $this->assertStringContainsString('slate-200', $presenter->severityClasses($this->makeNotification(['type' => 'admin']), false));
        $this->assertStringContainsString('slate-50', $presenter->severityClasses($this->makeNotification(['type' => 'system']), true));
        $this->assertStringContainsString('slate-200', $presenter->severityClasses($this->makeNotification(['type' => 'system']), false));
    }

    public function test_title_and_message_prefer_payload_then_fallback(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertSame('Titre', $presenter->title($this->makeNotification(['type' => 'finance', 'title' => 'Titre'])));
        $this->assertSame('Finance', $presenter->title($this->makeNotification(['type' => 'finance'])));
        $this->assertSame('Bonjour', $presenter->message($this->makeNotification(['message' => 'Bonjour'])));
        $this->assertSame('Notification', $presenter->message($this->makeNotification([])));
    }

    public function test_context_filters_to_present_values(): void
    {
        $presenter = app(NotificationPresenter::class);

        $context = $presenter->context($this->makeNotification([
            'rdv_id' => 7,
            'invoice_number' => 'INV-9',
            'zone_name' => 'Bruxelles',
            'service_label' => null,
            'google_email' => '',
        ]));

        $this->assertSame(7, $context['rdv_id']);
        $this->assertSame('INV-9', $context['invoice_number']);
        $this->assertSame('Bruxelles', $context['zone']);
        $this->assertArrayNotHasKey('service', $context);
        $this->assertArrayNotHasKey('google_email', $context);
    }

    public function test_searchable_text_aggregates_payload_fields(): void
    {
        $presenter = app(NotificationPresenter::class);

        $text = $presenter->searchableText($this->makeNotification([
            'type' => 'finance',
            'title' => 'Facture',
            'message' => 'Paiement',
            'zone_name' => 'Liege',
            'service_label' => 'Nettoyage',
            'invoice_number' => 'INV-77',
            'google_email' => 'a@b.com',
            'booking_reference' => 'BK-1',
            'rdv_id' => 99,
        ]));

        $this->assertStringContainsString('finance', $text);
        $this->assertStringContainsString('nettoyage', $text);
        $this->assertStringContainsString('inv-77', $text);
        $this->assertStringContainsString('bk-1', $text);
    }

    public function test_action_url_uses_payload_url_or_guards_without_user(): void
    {
        $presenter = app(NotificationPresenter::class);

        $this->assertSame('https://example.test/x', $presenter->actionUrl($this->makeNotification(['action_url' => 'https://example.test/x']), null));
        $this->assertSame('#', $presenter->actionUrl($this->makeNotification(['type' => 'finance']), null));
    }

    public function test_action_url_routes_by_role_and_type(): void
    {
        $presenter = app(NotificationPresenter::class);
        $admin = User::factory()->admin()->create();
        $employe = User::factory()->employe()->create();
        $client = User::factory()->client()->create();

        $this->assertSame(route('admin.feedbacks'), $presenter->actionUrl($this->makeNotification(['type' => 'feedback']), $admin));
        $this->assertSame(route('client.historique'), $presenter->actionUrl($this->makeNotification(['type' => 'feedback']), $client));
        $this->assertSame(route('admin.finance'), $presenter->actionUrl($this->makeNotification(['type' => 'finance']), $admin));
        $this->assertSame(route('client.dashboard'), $presenter->actionUrl($this->makeNotification(['type' => 'finance']), $client));
        $this->assertSame(route('admin.calendar.settings'), $presenter->actionUrl($this->makeNotification(['type' => 'calendar']), $admin));
        $this->assertSame(route('employe.google.calendar'), $presenter->actionUrl($this->makeNotification(['type' => 'calendar']), $employe));
        $this->assertSame(route('client.dashboard'), $presenter->actionUrl($this->makeNotification(['type' => 'calendar']), $client));
        $this->assertSame(route('admin.dashboard'), $presenter->actionUrl($this->makeNotification(['type' => 'system']), $admin));
        $this->assertSame(route('employe.dashboard'), $presenter->actionUrl($this->makeNotification(['type' => 'system']), $employe));
        $this->assertSame(route('client.rendezvous.index'), $presenter->actionUrl($this->makeNotification(['type' => 'system']), $client));
    }
}
