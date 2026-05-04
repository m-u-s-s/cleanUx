<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class RecurringBookingTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2025-01-15 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_standard_client_can_create_recurring_series_with_structured_occurrences(): void
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();
        $bookingDate = now()->next(Carbon::TUESDAY)->toDateString();

        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $bookingDate]);

        $this->actingAs($client);

        Livewire::test(PrendreRendezVous::class)
            ->set('selected_service_identifier', $context['service']->code ?: $context['service']->slug)
            ->set('type_lieu', 'appartement')
            ->set('frequence', 'hebdomadaire')
            ->set('surface', 'moins_50')
            ->set('adresse', 'Rue de la Loi 1')
            ->set('ville', $context['postalCode']->city_name)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('telephone_client', '0470000000')
            ->set('priorite', 'normale')
            ->set('rdvDate', $bookingDate)
            ->set('rdvHeure', '10:00')
            ->set('is_recurrent', true)
            ->set('recurrence_frequency', 'weekly')
            ->set('recurrence_interval', 1)
            ->set('recurrence_count', 4)
            ->set('recurrence_days', [
                now()->parse($bookingDate)->isoWeekday(),
            ])
            ->call('validerRdv')
            ->assertHasNoErrors()
            ->assertSet('step', 5);

        $records = RendezVous::query()
            ->where('client_id', $client->id)
            ->orderBy('series_position')
            ->get();

        $this->assertCount(4, $records);
        $this->assertTrue($records->first()->is_series_master);
        $this->assertSame('active', $records->first()->series_status);
        $this->assertSame('weekly', $records->first()->recurrence_frequency);
        $this->assertSame(1, $records->first()->recurrence_interval);
        $this->assertSame(4, $records->first()->recurrence_count);
        $this->assertNotNull($records->first()->recurring_series_id);
        $this->assertSame($records->pluck('recurring_series_id')->unique()->count(), 1);
        $this->assertSame([1, 2, 3, 4], $records->pluck('series_position')->all());
        $this->assertTrue($records->every(fn (RendezVous $rdv) => $rdv->service_zone_id === $context['zone']->id));
        $this->assertTrue($records->every(fn (RendezVous $rdv) => $rdv->service_catalog_id === $context['service']->id));
        $this->assertTrue($records->every(fn (RendezVous $rdv) => $rdv->postal_code_id === $context['postalCode']->id));
        $this->assertTrue($records->every(fn (RendezVous $rdv) => data_get($rdv->zone_snapshot, 'zone.id') === $context['zone']->id));
    }
}
