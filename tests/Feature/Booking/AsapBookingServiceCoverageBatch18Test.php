<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Booking\AsapBookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/** Couvre les branches de AsapBookingService::findBestAsapSlot : - chemin "employé préféré" éligible + disponible (retour immédiat), - employé préféré introuvable (find null) puis repli sur resolveBest, - employé préféré présent mais ne couvrant pas la zone (court-circuit) → repli, - employé préféré couvrant la zone mais occupé (indispo) → repli, - aucun employé éligible → balayage jusqu'à la deadline puis retour null. */
class AsapBookingServiceCoverageBatch18Test extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10, 0, 0, 'Europe/Brussels'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function asapService(): AsapBookingService
    {
        return app(AsapBookingService::class);
    }

    private function eligibleEmployeeOnZone(ServiceZone $zone): User
    {
        $employee = User::factory()->employe()->create();

        $this->assignEmployeeToZone($employee, $zone, [], [
            'date' => now('Europe/Brussels')->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '20:00:00',
        ]);

        return $employee;
    }

    #[Test]
    public function preferred_employee_available_is_selected_immediately(): void
    {
        $context = $this->createCoverageContext();
        $zone = $context['zone'];
        $today = now('Europe/Brussels')->toDateString();

        $preferred = $this->eligibleEmployeeOnZone($zone);

        $slot = $this->asapService()->findBestAsapSlot($zone, 90, $preferred->id, 2);

        $this->assertNotNull($slot);
        $this->assertSame($preferred->id, $slot['employee']->id);
        $this->assertSame($today, $slot['date']);
        $this->assertArrayHasKey('heure', $slot);
        $this->assertArrayHasKey('deadline', $slot);
    }

    #[Test]
    public function unknown_preferred_employee_falls_back_to_resolver(): void
    {
        $context = $this->createCoverageContext();
        $zone = $context['zone'];

        $fallback = $this->eligibleEmployeeOnZone($zone);

        // preferredEmployeeId pointe vers un id inexistant → User::find() renvoie null,
        // le bloc préféré est sauté, le resolver générique prend le relais.
        $slot = $this->asapService()->findBestAsapSlot($zone, 90, 999999, 2);

        $this->assertNotNull($slot);
        $this->assertSame($fallback->id, $slot['employee']->id);
    }

    #[Test]
    public function preferred_employee_not_covering_zone_falls_back_to_resolver(): void
    {
        $context = $this->createCoverageContext();
        $zone = $context['zone'];

        // Présent mais NON rattaché à la zone → employeeCanCoverZone() renvoie false,
        // la condition court-circuite et le resolver choisit un autre employé.
        $preferred = User::factory()->employe()->create();
        $fallback = $this->eligibleEmployeeOnZone($zone);

        $slot = $this->asapService()->findBestAsapSlot($zone, 90, $preferred->id, 2);

        $this->assertNotNull($slot);
        $this->assertSame($fallback->id, $slot['employee']->id);
    }

    #[Test]
    public function preferred_employee_busy_for_every_slot_falls_back_to_resolver(): void
    {
        $context = $this->createCoverageContext();
        $zone = $context['zone'];
        $today = now('Europe/Brussels')->toDateString();

        // Couvre la zone mais SATURÉ toute la fenêtre ASAP par un RDV confirmé
        // 10:00-23:00 → employeeIsAvailableForSlot() renvoie false à chaque curseur.
        $preferred = $this->eligibleEmployeeOnZone($zone);
        $otherClient = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);
        Booking::factory()->create([
            'client_id' => $otherClient->id,
            'employe_id' => $preferred->id,
            'service_zone_id' => $zone->id,
            'date' => $today,
            'heure' => '10:00:00',
            'duree' => 780,
            'estimated_duration_minutes' => 780,
            'booking_mode' => 'scheduled',
            'status' => 'confirme',
        ]);

        $fallback = $this->eligibleEmployeeOnZone($zone);

        $slot = $this->asapService()->findBestAsapSlot($zone, 90, $preferred->id, 2);

        $this->assertNotNull($slot);
        $this->assertSame($fallback->id, $slot['employee']->id);
    }

    #[Test]
    public function returns_null_when_no_employee_can_serve_the_window(): void
    {
        $context = $this->createCoverageContext();
        $zone = $context['zone'];

        // Aucun employé éligible : le balayage atteint la deadline sans candidat.
        $slot = $this->asapService()->findBestAsapSlot($zone, 90, null, 1);

        $this->assertNull($slot);
    }
}
