<?php

namespace Tests\Unit;

use App\Support\Domain\BookingStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookingStatusTest extends TestCase
{
    #[Test]
    public function it_exposes_consistent_booking_status_groups(): void
    {
        $this->assertContains(BookingStatus::EN_ATTENTE, BookingStatus::all());
        $this->assertContains(BookingStatus::CONFIRME, BookingStatus::active());
        $this->assertContains(BookingStatus::TERMINE, BookingStatus::final());
        $this->assertContains(BookingStatus::EN_ATTENTE, BookingStatus::clientEditable());
        $this->assertContains(BookingStatus::SUR_PLACE, BookingStatus::clientLocked());
        $this->assertSame('warning', BookingStatus::notificationSeverity(BookingStatus::EN_ATTENTE));
        $this->assertSame('confirmée', BookingStatus::label(BookingStatus::CONFIRME));
    }

    #[Test]
    public function it_builds_a_dashboard_case_sql_expression(): void
    {
        $sql = BookingStatus::employeeDashboardCaseSql('status');

        $this->assertStringContainsString("CASE status", $sql);
        $this->assertStringContainsString("WHEN 'sur_place' THEN 1", $sql);
        $this->assertStringContainsString("WHEN 'refuse' THEN 6", $sql);
    }
}
