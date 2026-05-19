<?php

namespace Tests\Feature\Disputes;

use App\Events\Disputes\DisputeOpened;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\User;
use App\Services\Disputes\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DisputeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $client;
    protected User $provider;
    protected User $admin;
    protected Booking $booking;
    protected DisputeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->client()->create();
        $this->provider = User::factory()->create(['role' => 'employe']);
        $this->admin = User::factory()->admin()->create();

        $this->booking = Booking::create([
            'client_id' => $this->client->id,
            'employe_id' => $this->provider->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 100,
        ]);

        $this->service = app(DisputeService::class);
    }

    public function test_client_can_open_dispute(): void
    {
        Event::fake([DisputeOpened::class]);

        $case = $this->service->open($this->client, [
            'subject' => 'Service mal effectué',
            'description' => 'Le prestataire est parti après 30 min sans finir.',
            'category' => 'quality',
            'priority' => 'high',
            'severity' => 'high',
            'booking_id' => $this->booking->id,
        ]);

        $this->assertNotNull($case->reference);
        $this->assertStringStartsWith('DSP-', $case->reference);
        $this->assertSame(ComplaintCase::STATUS_OPEN, $case->status);
        $this->assertSame((int) $this->booking->id, (int) $case->booking_id);
        $this->assertSame((int) $this->provider->id, (int) $case->provider_user_id);
        $this->assertNotNull($case->due_at);

        $this->assertSame(1, $case->events()->count());
        $this->assertSame(DisputeEvent::TYPE_OPENED, $case->events()->first()->type);

        Event::assertDispatched(DisputeOpened::class);
    }

    public function test_client_cannot_open_for_other_users_booking(): void
    {
        $otherClient = User::factory()->client()->create();

        $this->expectException(ValidationException::class);

        $this->service->open($otherClient, [
            'subject' => 'Test',
            'description' => 'Description suffisante',
            'category' => 'quality',
            'booking_id' => $this->booking->id,
        ]);
    }

    public function test_full_lifecycle_open_assign_message_resolve(): void
    {
        $case = $this->openTestCase();

        $this->service->assign($case, $this->admin);
        $case->refresh();
        $this->assertSame((int) $this->admin->id, (int) $case->assigned_to);
        $this->assertSame(ComplaintCase::STATUS_ASSIGNED, $case->status);

        $this->service->addMessage(
            $case,
            $this->admin,
            DisputeEvent::ROLE_ADMIN,
            "Nous étudions votre dossier.",
        );

        $case->refresh();
        $this->assertNotNull($case->first_response_at);
        $this->assertSame(3, $case->events()->count()); // opened + assigned + admin message

        $this->service->transition($case, ComplaintCase::STATUS_INVESTIGATING, $this->admin);
        $this->service->transition($case, ComplaintCase::STATUS_RESOLVED, $this->admin, 'Refund accordé');

        $case->refresh();
        $this->assertSame(ComplaintCase::STATUS_RESOLVED, $case->status);
        $this->assertNotNull($case->resolved_at);
        $this->assertTrue($case->isFinal());
    }

    public function test_message_after_resolution_is_rejected(): void
    {
        $case = $this->openTestCase();
        $this->service->transition($case, ComplaintCase::STATUS_RESOLVED, $this->admin);

        $this->expectException(ValidationException::class);

        $this->service->addMessage(
            $case->fresh(),
            $this->client,
            DisputeEvent::ROLE_CLIENT,
            "Encore un truc",
        );
    }

    public function test_provider_message_transitions_from_awaiting_provider_to_investigating(): void
    {
        $case = $this->openTestCase();
        $this->service->transition($case, ComplaintCase::STATUS_AWAITING_PROVIDER, $this->admin);

        $this->service->addMessage(
            $case->fresh(),
            $this->provider,
            DisputeEvent::ROLE_PROVIDER,
            "Je conteste la version du client.",
        );

        $this->assertSame(ComplaintCase::STATUS_INVESTIGATING, $case->fresh()->status);
    }

    public function test_escalate_increments_level_and_changes_due_at(): void
    {
        $case = $this->openTestCase();
        $originalDue = $case->due_at?->copy();

        $this->service->escalate($case, 'SLA dépassé');
        $case->refresh();

        $this->assertSame(1, $case->escalation_level);
        $this->assertSame(ComplaintCase::STATUS_ESCALATED, $case->status);
        $this->assertNotNull($case->escalated_at);
        if ($originalDue) {
            // due_at sera recalculé selon priority='urgent' donc plus tôt
            $this->assertNotEquals($originalDue->toIso8601String(), $case->due_at->toIso8601String());
        }
    }

    public function test_event_visibility_filters_for_provider(): void
    {
        $case = $this->openTestCase();

        $this->service->addMessage(
            $case,
            $this->admin,
            DisputeEvent::ROLE_ADMIN,
            "Note privée admin",
            DisputeEvent::VISIBILITY_PRIVATE,
        );

        $this->service->addMessage(
            $case,
            $this->admin,
            DisputeEvent::ROLE_ADMIN,
            "Message visible client uniquement",
            DisputeEvent::VISIBILITY_CLIENT,
        );

        $providerVisible = $case->events()
            ->visibleTo(DisputeEvent::ROLE_PROVIDER)
            ->get();

        foreach ($providerVisible as $event) {
            $this->assertNotSame(DisputeEvent::VISIBILITY_PRIVATE, $event->visibility);
            $this->assertNotSame(DisputeEvent::VISIBILITY_CLIENT, $event->visibility);
        }
    }

    protected function openTestCase(): ComplaintCase
    {
        return $this->service->open($this->client, [
            'subject' => 'Test',
            'description' => 'Description suffisante pour passer la validation',
            'category' => 'quality',
            'priority' => 'normal',
            'severity' => 'medium',
            'booking_id' => $this->booking->id,
        ]);
    }
}
