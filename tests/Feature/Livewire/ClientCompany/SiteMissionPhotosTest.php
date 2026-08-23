<?php

namespace Tests\Feature\Livewire\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\SiteMissionPhotos;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionMedia;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Espace société B2B P3 — photos avant/après d'une intervention, accessibles au gestionnaire pour les missions de SON organisation uniquement (anti-IDOR). */
class SiteMissionPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(OrganizationAccount $org): User
    {
        $user = User::factory()->client()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function missionForOrg(OrganizationAccount $org): Mission
    {
        $booking = Booking::factory()->create(['customer_organization_id' => $org->id]);
        $mission = Mission::factory()->create(['booking_id' => $booking->id]);

        MissionMedia::create(['mission_id' => $mission->id, 'media_type' => 'before_photo', 'path' => 'missions/before/a.jpg']);
        MissionMedia::create(['mission_id' => $mission->id, 'media_type' => 'after_photo', 'path' => 'missions/after/b.jpg']);

        return $mission;
    }

    public function test_org_member_sees_before_and_after_photos_of_org_mission(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $member = $this->memberOf($org);
        $mission = $this->missionForOrg($org);

        $this->actingAs($member);

        Livewire::test(SiteMissionPhotos::class, ['mission' => $mission])
            ->assertOk()
            ->assertViewHas('beforePhotos', fn ($p) => count($p) === 1)
            ->assertViewHas('afterPhotos', fn ($p) => count($p) === 1);
    }

    public function test_member_of_another_org_is_forbidden(): void
    {
        $orgA = OrganizationAccount::factory()->clientCompany()->create();
        $orgB = OrganizationAccount::factory()->clientCompany()->create();
        $intruder = $this->memberOf($orgA);
        $missionB = $this->missionForOrg($orgB);

        $this->actingAs($intruder);

        Livewire::test(SiteMissionPhotos::class, ['mission' => $missionB])
            ->assertForbidden();
    }
}
