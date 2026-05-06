<?php

namespace Tests\Feature\ReviewFixes;

use App\Events\Tasks\TaskAssigned;
use App\Events\Tasks\TaskStatusChanged;
use App\Models\Booking;
use App\Models\OrganizationAccount;
use App\Models\Task;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests anti-régression pour les 4 fix-ups appliqués après review.
 *
 * Chaque test cible UN bug précis identifié dans REVIEW_REPORT.md.
 */
class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────
    // FIX 1 — User accessor organization_account_id
    // ──────────────────────────────────────────────────────

    public function test_user_organization_account_id_accessor_returns_current_organization_id(): void
    {
        $org  = OrganizationAccount::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);

        $this->assertSame($org->id, $user->organization_account_id);
    }

    public function test_user_organization_account_id_returns_null_when_no_org(): void
    {
        $user = User::factory()->create([
            'current_organization_id' => null,
        ]);

        $this->assertNull($user->organization_account_id);
    }

    // ──────────────────────────────────────────────────────
    // FIX 2 — Booking status helpers utilisent vraies constantes FR
    // ──────────────────────────────────────────────────────

    public function test_is_pending_works_with_french_constant(): void
    {
        $booking = $this->makeBooking(['status' => BookingStatus::EN_ATTENTE]);
        $this->assertTrue($booking->isPending());
        $this->assertFalse($booking->isConfirmed());
    }

    public function test_is_pending_also_works_with_legacy_english_status(): void
    {
        // Bookings créés via assistant LLM utilisent 'pending'
        $booking = $this->makeBooking(['status' => 'pending']);
        $this->assertTrue($booking->isPending());
    }

    public function test_is_confirmed_with_french_constant(): void
    {
        $booking = $this->makeBooking(['status' => BookingStatus::CONFIRME]);
        $this->assertTrue($booking->isConfirmed());
        $this->assertFalse($booking->isPending());
    }

    public function test_is_cancelled_with_both_annule_and_refuse(): void
    {
        $b1 = $this->makeBooking(['status' => BookingStatus::ANNULE]);
        $b2 = $this->makeBooking(['status' => BookingStatus::REFUSE]);
        $b3 = $this->makeBooking(['status' => 'cancelled']);

        $this->assertTrue($b1->isCancelled());
        $this->assertTrue($b2->isCancelled());
        $this->assertTrue($b3->isCancelled());
    }

    public function test_is_completed_with_termine_and_completed(): void
    {
        $b1 = $this->makeBooking(['status' => BookingStatus::TERMINE]);
        $b2 = $this->makeBooking(['status' => 'completed']);

        $this->assertTrue($b1->isCompleted());
        $this->assertTrue($b2->isCompleted());
    }

    public function test_is_in_progress_recognizes_en_route_and_sur_place(): void
    {
        $b1 = $this->makeBooking(['status' => BookingStatus::EN_ROUTE]);
        $b2 = $this->makeBooking(['status' => BookingStatus::SUR_PLACE]);

        $this->assertTrue($b1->isInProgress());
        $this->assertTrue($b2->isInProgress());
    }

    public function test_is_final_returns_true_for_terminal_states(): void
    {
        $cancelled = $this->makeBooking(['status' => BookingStatus::ANNULE]);
        $completed = $this->makeBooking(['status' => BookingStatus::TERMINE]);
        $pending   = $this->makeBooking(['status' => BookingStatus::EN_ATTENTE]);

        $this->assertTrue($cancelled->isFinal());
        $this->assertTrue($completed->isFinal());
        $this->assertFalse($pending->isFinal());
    }

    // ──────────────────────────────────────────────────────
    // FIX 3 — CreateBookingTool fournit booking_reference
    // ──────────────────────────────────────────────────────

    public function test_create_booking_tool_generates_unique_reference(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $tool = app(\App\Services\Assistant\Tools\Implementations\CreateBookingTool::class);

        $result = $tool->execute($user, [
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'place_type'     => 'apartment',
            'surface_m2'     => 60,
            'address'        => 'Rue Test 1',
            'city'           => 'Bruxelles',
            'postal_code'    => '1000',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertStringStartsWith('CUX-', $result['booking_reference']);
        $this->assertSame(1, Booking::where('booking_reference', $result['booking_reference'])->count());
    }

    public function test_create_booking_tool_view_url_falls_back_to_existing_route(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $tool = app(\App\Services\Assistant\Tools\Implementations\CreateBookingTool::class);
        $result = $tool->execute($user, [
            'scheduled_date' => '2026-08-11',
            'scheduled_time' => '14:00',
            'place_type'     => 'office',
            'surface_m2'     => 100,
            'address'        => 'Rue Test 2',
            'city'           => 'Liège',
            'postal_code'    => '4000',
        ]);

        // L'URL ne doit jamais être vide ni provoquer RouteNotFoundException
        $this->assertNotEmpty($result['view_url']);
        $this->assertNotSame('#', $result['view_url']); // au moins une route a marché
    }

    // ──────────────────────────────────────────────────────
    // FIX 4 — TaskAssigned/TaskStatusChanged channel_id branching
    // ──────────────────────────────────────────────────────

    public function test_task_assigned_includes_channel_when_task_has_channel_id(): void
    {
        $org      = OrganizationAccount::factory()->create();
        $assignee = User::factory()->create();

        // Construit un Task "léger" sans passer par la migration tasks (qui peut ne pas avoir channel_id)
        $task = new Task();
        $task->id = 100;
        $task->organization_account_id = $org->id;
        $task->channel_id = 42;
        $task->assigned_to_user_id = $assignee->id;

        $event = new TaskAssigned($task, $assignee);
        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());

        // AVANT le fix : 'private-channel.42' était absent (property_exists failed)
        // APRÈS le fix : il doit être présent
        $this->assertContains('private-channel.42', $names);
    }

    public function test_task_status_changed_skips_channel_when_no_channel_id(): void
    {
        $org      = OrganizationAccount::factory()->create();
        $assignee = User::factory()->create();

        $task = new Task();
        $task->id = 101;
        $task->organization_account_id = $org->id;
        $task->channel_id = null; // pas de canal
        $task->assigned_to_user_id = $assignee->id;

        $event = new TaskStatusChanged($task, 'pending', 'in_progress');
        $names = array_map(fn ($c) => $c->name, $event->broadcastOn());

        // Pas de private-channel.* car pas de channel_id
        $this->assertEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'private-channel.')));
        $this->assertContains('private-presence-org.' . $org->id, $names);
        $this->assertContains('private-user.' . $assignee->id, $names);
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    private function makeBooking(array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-' . strtoupper(\Illuminate\Support\Str::random(6)),
            'status'            => 'pending',
            'booking_mode'      => 'scheduled',
            'priority'          => 'normal',
            'currency'          => 'EUR',
        ], $overrides));
    }
}
