<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\EmployeeZoneAssignment;
use App\Models\Feedback;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\AdminScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminScopeCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    private function zoneScopedAdmin(ServiceZone $zone): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'managed_service_zone_id' => $zone->id,
        ]);
    }

    private function globalAdmin(): User
    {
        return User::factory()->admin()->create([
            'is_active' => true,
            'managed_service_zone_id' => null,
        ]);
    }

    public function test_scope_rendez_vous_leaves_query_untouched_for_non_admin(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        Booking::factory()->create(['service_zone_id' => $zoneA->id]);
        Booking::factory()->create(['service_zone_id' => $zoneB->id]);

        $client = User::factory()->client()->create();

        $rows = AdminScope::scopeRendezVousQuery(Booking::query(), $client)->get();
        $this->assertCount(2, $rows);

        // Null admin short-circuits the same way.
        $nullRows = AdminScope::scopeRendezVousQuery(Booking::query(), null)->get();
        $this->assertCount(2, $nullRows);
    }

    public function test_scope_rendez_vous_filters_for_zone_scoped_admin_but_not_global(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $inZone = Booking::factory()->create(['service_zone_id' => $zoneA->id]);
        Booking::factory()->create(['service_zone_id' => $zoneB->id]);

        $scoped = AdminScope::scopeRendezVousQuery(Booking::query(), $this->zoneScopedAdmin($zoneA))->get();
        $this->assertCount(1, $scoped);
        $this->assertSame($inZone->id, $scoped->first()->id);

        $global = AdminScope::scopeRendezVousQuery(Booking::query(), $this->globalAdmin())->get();
        $this->assertCount(2, $global);
    }

    public function test_scope_feedback_filters_through_rendez_vous_relation(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();

        $rdvA = Booking::factory()->create(['service_zone_id' => $zoneA->id]);
        $rdvB = Booking::factory()->create(['service_zone_id' => $zoneB->id]);
        $fbA = Feedback::factory()->forRendezVous($rdvA)->create();
        Feedback::factory()->forRendezVous($rdvB)->create();

        $scoped = AdminScope::scopeFeedbackQuery(Feedback::query(), $this->zoneScopedAdmin($zoneA))->get();
        $this->assertCount(1, $scoped);
        $this->assertSame($fbA->id, $scoped->first()->id);

        $global = AdminScope::scopeFeedbackQuery(Feedback::query(), $this->globalAdmin())->get();
        $this->assertCount(2, $global);

        $passthrough = AdminScope::scopeFeedbackQuery(Feedback::query(), null)->get();
        $this->assertCount(2, $passthrough);
    }

    public function test_scope_user_includes_self_zone_members_and_assignments(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $admin = $this->zoneScopedAdmin($zoneA);

        $primaryMember = User::factory()->employe()->create(['primary_service_zone_id' => $zoneA->id]);
        $managedMember = User::factory()->admin()->create(['managed_service_zone_id' => $zoneA->id]);
        $assignedMember = User::factory()->employe()->create();
        EmployeeZoneAssignment::factory()->create([
            'user_id' => $assignedMember->id,
            'service_zone_id' => $zoneA->id,
            'is_active' => true,
        ]);
        $outsider = User::factory()->employe()->create(['primary_service_zone_id' => $zoneB->id]);

        $ids = AdminScope::scopeUserQuery(User::query(), $admin)->pluck('id');

        $this->assertTrue($ids->contains($admin->id));
        $this->assertTrue($ids->contains($primaryMember->id));
        $this->assertTrue($ids->contains($managedMember->id));
        $this->assertTrue($ids->contains($assignedMember->id));
        $this->assertFalse($ids->contains($outsider->id));

        // Global admin sees everyone.
        $allIds = AdminScope::scopeUserQuery(User::query(), $this->globalAdmin())->pluck('id');
        $this->assertTrue($allIds->contains($outsider->id));
    }

    public function test_can_access_rendez_vous_guards_and_zone_match(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $rdvA = Booking::factory()->create(['service_zone_id' => $zoneA->id]);
        $rdvB = Booking::factory()->create(['service_zone_id' => $zoneB->id]);

        $this->assertFalse(AdminScope::canAccessRendezVous(User::factory()->client()->create(), $rdvA));

        $inactive = User::factory()->admin()->create(['is_active' => false]);
        $this->assertFalse(AdminScope::canAccessRendezVous($inactive, $rdvA));

        $scoped = $this->zoneScopedAdmin($zoneA);
        $this->assertTrue(AdminScope::canAccessRendezVous($scoped, $rdvA));
        $this->assertFalse(AdminScope::canAccessRendezVous($scoped, $rdvB));

        $this->assertTrue(AdminScope::canAccessRendezVous($this->globalAdmin(), $rdvB));
    }

    public function test_can_access_feedback_handles_missing_and_present_rendez_vous(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $rdvA = Booking::factory()->create(['service_zone_id' => $zoneA->id]);
        $feedbackWithRdv = Feedback::factory()->forRendezVous($rdvA)->create();
        $orphanFeedback = Feedback::factory()->create(['rendez_vous_id' => null]);

        $scoped = $this->zoneScopedAdmin($zoneA);
        $global = $this->globalAdmin();

        // Orphan feedback: zone-scoped admin denied, global admin allowed.
        $this->assertFalse(AdminScope::canAccessFeedback($scoped, $orphanFeedback));
        $this->assertTrue(AdminScope::canAccessFeedback($global, $orphanFeedback));

        // Delegates to rendez-vous check when relation present.
        $this->assertTrue(AdminScope::canAccessFeedback($scoped, $feedbackWithRdv));

        $this->assertFalse(AdminScope::canAccessFeedback(User::factory()->client()->create(), $feedbackWithRdv));
    }

    public function test_can_access_user_covers_self_global_and_zone_branches(): void
    {
        $zoneA = ServiceZone::factory()->create();
        $zoneB = ServiceZone::factory()->create();
        $admin = $this->zoneScopedAdmin($zoneA);

        // Non-admin caller.
        $this->assertFalse(AdminScope::canAccessUser(User::factory()->client()->create(), $admin));

        // Self access.
        $this->assertTrue(AdminScope::canAccessUser($admin, $admin));

        // Global admin reaches anyone.
        $outsider = User::factory()->employe()->create(['primary_service_zone_id' => $zoneB->id]);
        $this->assertTrue(AdminScope::canAccessUser($this->globalAdmin(), $outsider));

        // Zone-scoped: primary zone match.
        $primaryMember = User::factory()->employe()->create(['primary_service_zone_id' => $zoneA->id]);
        $this->assertTrue(AdminScope::canAccessUser($admin, $primaryMember));

        // Zone-scoped: managed zone match.
        $managedMember = User::factory()->admin()->create(['managed_service_zone_id' => $zoneA->id]);
        $this->assertTrue(AdminScope::canAccessUser($admin, $managedMember));

        // Zone-scoped: active assignment match.
        $assignedMember = User::factory()->employe()->create();
        EmployeeZoneAssignment::factory()->create([
            'user_id' => $assignedMember->id,
            'service_zone_id' => $zoneA->id,
            'is_active' => true,
        ]);
        $this->assertTrue(AdminScope::canAccessUser($admin, $assignedMember));

        // Zone-scoped: outsider denied.
        $this->assertFalse(AdminScope::canAccessUser($admin, $outsider));
    }
}
