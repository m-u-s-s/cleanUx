<?php

namespace Tests\Feature;

use App\Models\EmployeeZoneAssignment;
use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use App\Notifications\EmployeReaffectationSuggestionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendRendezVousRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassignment_suggestion_prefers_employee_covering_same_zone(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $zoneA = ServiceZone::factory()->create(['name' => 'Zone A']);
        $zoneB = ServiceZone::factory()->create(['name' => 'Zone B']);

        $overloaded = User::factory()->employe()->create(['name' => 'Employé Surchargé']);
        $otherZoneEmployee = User::factory()->employe()->create(['name' => 'Employé Hors Zone']);
        $sameZoneEmployee = User::factory()->employe()->create(['name' => 'Employé Zone A']);

        EmployeeZoneAssignment::factory()->create([
            'user_id' => $sameZoneEmployee->id,
            'service_zone_id' => $zoneA->id,
            'is_active' => true,
        ]);

        EmployeeZoneAssignment::factory()->create([
            'user_id' => $otherZoneEmployee->id,
            'service_zone_id' => $zoneB->id,
            'is_active' => true,
        ]);

        $client = User::factory()->client()->create();

        Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $overloaded->id,
            'service_zone_id' => $zoneA->id,
            'date' => today()->toDateString(),
            'heure' => '09:00:00',
            'status' => 'en_attente',
            'duree_estimee' => 480,
        ]);

        Artisan::call('app:send-rendezvous-reminders');

        Notification::assertSentTo($admin, EmployeReaffectationSuggestionNotification::class, function (EmployeReaffectationSuggestionNotification $notification) use ($sameZoneEmployee) {
            return $notification->employeSuggere === $sameZoneEmployee->name;
        });
    }
}
