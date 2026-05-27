<?php

namespace Tests\Unit\Assistant;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeResolution;
use App\Models\ProviderPresence;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\Assistant\Actions\ActionDetector;
use App\Services\Assistant\Actions\AssistantActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @covers \App\Services\Assistant\Actions\AssistantActionExecutor
 * @covers \App\Services\Assistant\Actions\ActionDetector
 * @group requires-mysql
 */
class AssistantWriteActionsTest extends TestCase
{
    use RefreshDatabase;

    private AssistantActionExecutor $executor;
    private ActionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') === 'sqlite') {
            $this->markTestSkipped('Write actions require full schema (MySQL/PostgreSQL)');
        }
        $this->executor = new AssistantActionExecutor();
        $this->detector = new ActionDetector();
    }

    // ──────────────────────────────────────────────────────
    // ActionDetector — write action keyword detection
    // ──────────────────────────────────────────────────────

    public function test_cancel_keyword_triggers_cancel_booking_for_client(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('Je souhaite annuler ma réservation CUX-ABC123', $user);

        $this->assertContains('cancel_booking', $actions);
    }

    public function test_cancel_keyword_does_not_trigger_for_provider(): void
    {
        $user = User::factory()->employe()->create();

        $actions = $this->detector->detectActions('annuler', $user);

        $this->assertNotContains('cancel_booking', $actions);
    }

    public function test_create_booking_keyword_triggers_for_client(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('Je veux créer une réservation de nettoyage', $user);

        $this->assertContains('create_booking', $actions);
    }

    public function test_go_online_keyword_triggers_update_availability_for_provider(): void
    {
        $user = User::factory()->employe()->create();

        $actions = $this->detector->detectActions('passer en ligne maintenant', $user);

        $this->assertContains('update_availability', $actions);
    }

    public function test_resolve_dispute_keyword_triggers_for_admin(): void
    {
        $user = User::factory()->admin()->create();

        $actions = $this->detector->detectActions('résoudre le litige #42', $user);

        $this->assertContains('resolve_dispute', $actions);
    }

    public function test_resolve_dispute_does_not_trigger_for_client(): void
    {
        $user = User::factory()->client()->create();

        $actions = $this->detector->detectActions('résoudre le litige #42', $user);

        $this->assertNotContains('resolve_dispute', $actions);
    }

    // ──────────────────────────────────────────────────────
    // isWriteAction
    // ──────────────────────────────────────────────────────

    public function test_is_write_action_returns_true_for_cancel_booking(): void
    {
        $this->assertTrue($this->executor->isWriteAction('cancel_booking'));
    }

    public function test_is_write_action_returns_true_for_create_booking(): void
    {
        $this->assertTrue($this->executor->isWriteAction('create_booking'));
    }

    public function test_is_write_action_returns_true_for_resolve_dispute(): void
    {
        $this->assertTrue($this->executor->isWriteAction('resolve_dispute'));
    }

    public function test_is_write_action_returns_false_for_read_only_action(): void
    {
        $this->assertFalse($this->executor->isWriteAction('list_bookings'));
        $this->assertFalse($this->executor->isWriteAction('platform_kpis'));
        $this->assertFalse($this->executor->isWriteAction('update_availability'));
    }

    // ──────────────────────────────────────────────────────
    // executeWrite — creates AssistantAction record
    // ──────────────────────────────────────────────────────

    public function test_execute_write_cancel_booking_returns_confirmation_payload(): void
    {
        $user    = User::factory()->client()->create();
        $booking = Booking::factory()->create(['customer_user_id' => $user->id, 'status' => 'confirme']);

        $result = $this->executor->executeWrite('cancel_booking', $user, ['booking_id' => $booking->id]);

        $this->assertSame('cancel_booking', $result['action']);
        $this->assertTrue($result['requires_confirmation']);
        $this->assertStringContainsString('Annuler', $result['summary']);
        $this->assertArrayHasKey('action_id', $result);
    }

    public function test_execute_write_persists_pending_assistant_action(): void
    {
        $user = User::factory()->client()->create();

        $this->executor->executeWrite('cancel_booking', $user, ['booking_id' => 99]);

        $this->assertDatabaseHas('assistant_actions', [
            'user_id'     => $user->id,
            'action_type' => 'cancel_booking',
            'status'      => AssistantAction::STATUS_PENDING_CONFIRMATION,
        ]);
    }

    public function test_execute_write_create_booking_summary_contains_date_and_city(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->executor->executeWrite('create_booking', $user, [
            'service_catalog_id' => 1,
            'address'            => '10 rue de la Paix',
            'city'               => 'Bruxelles',
            'postal_code'        => '1000',
            'scheduled_date'     => '2026-07-01',
            'scheduled_time'     => '09:00',
        ]);

        $this->assertStringContainsString('2026-07-01', $result['summary']);
        $this->assertStringContainsString('Bruxelles', $result['summary']);
    }

    // ──────────────────────────────────────────────────────
    // confirmAction — cancel_booking
    // ──────────────────────────────────────────────────────

    public function test_confirm_cancel_booking_cancels_and_returns_success_message(): void
    {
        $user    = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id'  => $user->id,
            'status'            => 'confirme',
            'booking_reference' => 'CUX-TEST01',
        ]);

        $pending = $this->executor->executeWrite('cancel_booking', $user, ['booking_id' => $booking->id]);
        $result  = $this->executor->confirmAction($pending['action_id'], $user);

        $this->assertStringContainsString('annulée', mb_strtolower($result));
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'annule']);
        $this->assertDatabaseHas('assistant_actions', [
            'id'     => $pending['action_id'],
            'status' => AssistantAction::STATUS_EXECUTED,
        ]);
    }

    public function test_confirm_action_not_found_returns_error(): void
    {
        $user   = User::factory()->client()->create();
        $result = $this->executor->confirmAction(999999, $user);

        $this->assertStringContainsString('introuvable', mb_strtolower($result));
    }

    public function test_confirm_action_wrong_user_returns_error(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $pending = $this->executor->executeWrite('cancel_booking', $owner, ['booking_id' => 1]);
        $result  = $this->executor->confirmAction($pending['action_id'], $other);

        $this->assertStringContainsString('introuvable', mb_strtolower($result));
    }

    // ──────────────────────────────────────────────────────
    // cancel_booking — edge cases
    // ──────────────────────────────────────────────────────

    public function test_cancel_already_cancelled_booking_returns_error(): void
    {
        $user    = User::factory()->client()->create();
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
            'status'           => 'annule',
        ]);

        $pending = $this->executor->executeWrite('cancel_booking', $user, ['booking_id' => $booking->id]);
        $result  = $this->executor->confirmAction($pending['action_id'], $user);

        $this->assertStringContainsString('erreur', mb_strtolower($result));
    }

    // ──────────────────────────────────────────────────────
    // update_availability (immediate, no confirmation)
    // ──────────────────────────────────────────────────────

    public function test_update_availability_online_sets_presence_record(): void
    {
        $user = User::factory()->employe()->create();

        $result = $this->executor->executeImmediateWrite('update_availability', $user, ['status' => 'online']);

        $this->assertStringContainsString('en ligne', $result);
        $this->assertDatabaseHas('provider_presence', [
            'provider_user_id' => $user->id,
            'status'           => ProviderPresence::STATUS_ONLINE,
        ]);
    }

    public function test_update_availability_invalid_status_returns_error(): void
    {
        $user   = User::factory()->employe()->create();
        $result = $this->executor->executeImmediateWrite('update_availability', $user, ['status' => 'sleeping']);

        $this->assertStringContainsString('invalide', mb_strtolower($result));
    }

    // ──────────────────────────────────────────────────────
    // resolve_dispute
    // ──────────────────────────────────────────────────────

    public function test_confirm_resolve_dispute_resolves_complaint_case(): void
    {
        $admin   = User::factory()->admin()->create();
        $dispute = ComplaintCase::factory()->create([
            'status'   => ComplaintCase::STATUS_OPEN,
            'category' => ComplaintCase::CATEGORY_QUALITY,
        ]);

        $pending = $this->executor->executeWrite('resolve_dispute', $admin, [
            'dispute_id' => $dispute->id,
            'resolution' => 'Remboursement accordé suite à prestation non conforme.',
        ]);
        $result = $this->executor->confirmAction($pending['action_id'], $admin);

        $this->assertStringContainsString('résolu', mb_strtolower($result));
        $this->assertDatabaseHas('complaint_cases', [
            'id'     => $dispute->id,
            'status' => ComplaintCase::STATUS_RESOLVED,
        ]);
        $this->assertDatabaseHas('dispute_resolutions', [
            'complaint_case_id' => $dispute->id,
            'status'            => DisputeResolution::STATUS_APPLIED,
        ]);
    }

    public function test_resolve_dispute_already_resolved_returns_error(): void
    {
        $admin   = User::factory()->admin()->create();
        $dispute = ComplaintCase::factory()->create(['status' => ComplaintCase::STATUS_RESOLVED]);

        $pending = $this->executor->executeWrite('resolve_dispute', $admin, [
            'dispute_id' => $dispute->id,
            'resolution' => 'Test.',
        ]);
        $result = $this->executor->confirmAction($pending['action_id'], $admin);

        $this->assertStringContainsString('introuvable', mb_strtolower($result));
    }

    // ──────────────────────────────────────────────────────
    // create_booking
    // ──────────────────────────────────────────────────────

    public function test_confirm_create_booking_creates_booking_record(): void
    {
        $user    = User::factory()->client()->create();
        $catalog = ServiceCatalog::factory()->create();

        $pending = $this->executor->executeWrite('create_booking', $user, [
            'service_catalog_id' => $catalog->id,
            'address'            => '5 avenue Louise',
            'city'               => 'Bruxelles',
            'postal_code'        => '1050',
            'scheduled_date'     => '2026-08-10',
            'scheduled_time'     => '14:00',
        ]);
        $result = $this->executor->confirmAction($pending['action_id'], $user);

        $this->assertStringContainsString('créée', $result);
        $this->assertDatabaseHas('bookings', [
            'customer_user_id'   => $user->id,
            'service_catalog_id' => $catalog->id,
            'city'               => 'Bruxelles',
            'status'             => 'en_attente',
        ]);
    }

    public function test_create_booking_missing_params_returns_error(): void
    {
        $user = User::factory()->client()->create();

        $pending = $this->executor->executeWrite('create_booking', $user, [
            'service_catalog_id' => 1,
            // missing address, city, postal_code, scheduled_date, scheduled_time
        ]);
        $result = $this->executor->confirmAction($pending['action_id'], $user);

        $this->assertStringContainsString('erreur', mb_strtolower($result));
    }
}
