<?php

namespace Tests\Unit;

use App\Support\Domain\MissionStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MissionStatusTest extends TestCase
{
    #[Test]
    public function it_exposes_consistent_mission_status_groups(): void
    {
        $this->assertContains(MissionStatus::ASSIGNED, MissionStatus::all());
        $this->assertContains(MissionStatus::EN_ROUTE, MissionStatus::trackable());
        $this->assertContains(MissionStatus::PLANNED, MissionStatus::canSetEnRoute());
        $this->assertContains(MissionStatus::ARRIVED, MissionStatus::canStart());
        $this->assertContains(MissionStatus::PAUSED, MissionStatus::canFinish());
    }

    #[Test]
    public function it_resolves_the_initial_status_from_assignment_presence(): void
    {
        $this->assertSame(MissionStatus::ASSIGNED, MissionStatus::initialFor(true));
        $this->assertSame(MissionStatus::PLANNED, MissionStatus::initialFor(false));
    }
}
