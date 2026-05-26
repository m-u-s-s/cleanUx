<?php

namespace Tests\Unit\Models;

use App\Support\Domain\BookingStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for BookingStatus domain object — status constants, groups, and labels.
 * Complements the existing BookingStatusTest with cancelled/active/completed groups.
 */
class BookingStatusGroupsTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────
    // Constants are defined and non-empty
    // ─────────────────────────────────────────────────────────────────

    public function test_status_constants_are_defined(): void
    {
        $this->assertSame('en_attente', BookingStatus::EN_ATTENTE);
        $this->assertSame('confirme',   BookingStatus::CONFIRME);
        $this->assertSame('en_route',   BookingStatus::EN_ROUTE);
        $this->assertSame('sur_place',  BookingStatus::SUR_PLACE);
        $this->assertSame('termine',    BookingStatus::TERMINE);
        $this->assertSame('annule',     BookingStatus::ANNULE);
        $this->assertSame('refuse',     BookingStatus::REFUSE);
    }

    // ─────────────────────────────────────────────────────────────────
    // Active statuses (in-progress bookings)
    // ─────────────────────────────────────────────────────────────────

    public function test_active_statuses_include_in_progress_states(): void
    {
        $active = BookingStatus::active();

        $this->assertContains(BookingStatus::CONFIRME,  $active);
        $this->assertContains(BookingStatus::EN_ROUTE,  $active);
        $this->assertContains(BookingStatus::SUR_PLACE, $active);
    }

    public function test_active_statuses_exclude_terminal_states(): void
    {
        $active = BookingStatus::active();

        $this->assertNotContains(BookingStatus::TERMINE, $active);
        $this->assertNotContains(BookingStatus::ANNULE,  $active);
        $this->assertNotContains(BookingStatus::REFUSE,  $active);
    }

    // ─────────────────────────────────────────────────────────────────
    // Final (terminal) statuses
    // ─────────────────────────────────────────────────────────────────

    public function test_final_statuses_include_completed_and_cancelled(): void
    {
        $final = BookingStatus::final();

        $this->assertContains(BookingStatus::TERMINE, $final);
        $this->assertContains(BookingStatus::ANNULE,  $final);
        $this->assertContains(BookingStatus::REFUSE,  $final);
    }

    public function test_final_statuses_exclude_active_states(): void
    {
        $final = BookingStatus::final();

        $this->assertNotContains(BookingStatus::EN_ATTENTE, $final);
        $this->assertNotContains(BookingStatus::CONFIRME,   $final);
        $this->assertNotContains(BookingStatus::SUR_PLACE,  $final);
    }

    // ─────────────────────────────────────────────────────────────────
    // all() is a superset of active() and final()
    // ─────────────────────────────────────────────────────────────────

    public function test_all_statuses_is_superset_of_active_and_final(): void
    {
        $all    = BookingStatus::all();
        $active = BookingStatus::active();
        $final  = BookingStatus::final();

        foreach ($active as $status) {
            $this->assertContains($status, $all, "active status '$status' missing from all()");
        }

        foreach ($final as $status) {
            $this->assertContains($status, $all, "final status '$status' missing from all()");
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Labels are non-empty strings for all statuses
    // ─────────────────────────────────────────────────────────────────

    public function test_every_status_has_a_non_empty_label(): void
    {
        foreach (BookingStatus::all() as $status) {
            $label = BookingStatus::label($status);
            $this->assertNotEmpty($label, "Label is empty for status '$status'");
            $this->assertIsString($label);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Notification severity is valid for all statuses
    // ─────────────────────────────────────────────────────────────────

    public function test_notification_severity_returns_valid_level_for_all_statuses(): void
    {
        $validSeverities = ['info', 'success', 'warning', 'danger'];

        foreach (BookingStatus::all() as $status) {
            $severity = BookingStatus::notificationSeverity($status);
            $this->assertContains(
                $severity,
                $validSeverities,
                "Unexpected severity '$severity' for status '$status'"
            );
        }
    }
}
