<?php

namespace Tests\Unit;

use App\Services\Missions\TeamLeadOperationsService;
use PHPUnit\Framework\TestCase;

class TeamLeadOperationsServiceTest extends TestCase
{
    public function test_service_can_be_instantiated(): void
    {
        $service = new TeamLeadOperationsService();

        $this->assertInstanceOf(TeamLeadOperationsService::class, $service);
    }
}
