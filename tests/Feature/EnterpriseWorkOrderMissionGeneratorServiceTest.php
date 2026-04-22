<?php

namespace Tests\Feature;

use App\Services\Missions\EnterpriseWorkOrderMissionGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseWorkOrderMissionGeneratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_is_resolvable(): void
    {
        $service = $this->app->make(EnterpriseWorkOrderMissionGeneratorService::class);

        $this->assertInstanceOf(EnterpriseWorkOrderMissionGeneratorService::class, $service);
    }
}
