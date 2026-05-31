<?php

namespace Tests\Feature\Relations;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderOrgPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_writes_provider_organization_from_worker_profile(): void
    {
        $org = OrganizationAccount::factory()->create();
        $worker = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $worker->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $booking = Booking::factory()->create();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $assignment = MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $worker->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'notification_sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        app(MissionDispatchService::class)->accept($assignment);

        $this->assertSame($org->id, $mission->fresh()->provider_organization_id);
        $this->assertSame($worker->id, $mission->fresh()->lead_provider_user_id);
    }

    public function test_independent_worker_yields_null_provider_organization(): void
    {
        $worker = User::factory()->create();
        ProviderProfile::create([
            'user_id' => $worker->id,
            'organization_account_id' => null,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $booking = Booking::factory()->create();
        $mission = Mission::create(['booking_id' => $booking->id, 'status' => 'planned']);

        $assignment = MissionAssignment::create([
            'mission_id' => $mission->id,
            'user_id' => $worker->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'notification_sent_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        app(MissionDispatchService::class)->accept($assignment);

        $this->assertNull($mission->fresh()->provider_organization_id);
        $this->assertSame($worker->id, $mission->fresh()->lead_provider_user_id);
    }
}
