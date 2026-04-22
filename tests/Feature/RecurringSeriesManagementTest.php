<?php

namespace Tests\Feature;

use App\Actions\Booking\CancelRecurringSeriesAction;
use App\Livewire\Admin\EditRecurringBooking as AdminEditRecurringBooking;
use App\Livewire\Client\EditRecurringBooking as ClientEditRecurringBooking;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class RecurringSeriesManagementTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    public function test_client_can_update_single_occurrence_without_touching_rest_of_series(): void
    {
        [$client, $employee, $records] = $this->createSeries();
        $target = $records[1];
        $firstDate = $records[0]->date->toDateString();
        $thirdHour = substr((string) $records[2]->heure, 0, 5);

        $this->actingAs($client);

        Livewire::test(ClientEditRecurringBooking::class, ['rendezVous' => $target])
            ->set('scope', 'occurrence')
            ->set('editDate', $target->date->copy()->addDay()->toDateString())
            ->set('editHeure', '14:30')
            ->call('saveChanges')
            ->assertHasNoErrors();

        $this->assertSame('14:30', substr((string) $target->fresh()->heure, 0, 5));
        $this->assertSame($firstDate, $records[0]->fresh()->date->toDateString());
        $this->assertSame($thirdHour, substr((string) $records[2]->fresh()->heure, 0, 5));
    }

    public function test_admin_can_update_entire_series(): void
    {
        [$client, $employee, $records, $admin] = $this->createSeries(withAdmin: true);
        $target = $records[0];

        $this->actingAs($admin);

        Livewire::test(AdminEditRecurringBooking::class, ['rendezVous' => $target])
            ->set('scope', 'series')
            ->set('editDate', $target->date->copy()->addDays(2)->toDateString())
            ->set('editHeure', '15:00')
            ->call('saveChanges')
            ->assertHasNoErrors();

        $this->assertTrue(
            RendezVous::query()->where('recurring_series_id', $target->recurring_series_id)->get()->every(
                fn ($rdv) => substr((string) $rdv->heure, 0, 5) === '15:00'
            )
        );
    }

    public function test_pause_resume_and_cancel_future_series_work(): void
    {
        [$client, $employee, $records] = $this->createSeries();
        $target = $records[1];

        $this->actingAs($client);

        Livewire::test(ClientEditRecurringBooking::class, ['rendezVous' => $target])
            ->call('pauseSeries', 'future')
            ->assertHasNoErrors();

        $paused = RendezVous::query()->where('recurring_series_id', $target->recurring_series_id)->orderBy('series_position')->get();
        $this->assertSame('active', $paused[0]->series_status);
        $this->assertSame('paused', $paused[1]->series_status);
        $this->assertSame('paused', $paused[2]->series_status);

        app(CancelRecurringSeriesAction::class)->resume($target->fresh(), 'future');
        $resumed = RendezVous::query()->where('recurring_series_id', $target->recurring_series_id)->orderBy('series_position')->get();
        $this->assertTrue($resumed->skip(1)->every(fn ($rdv) => $rdv->series_status === 'active'));

        app(CancelRecurringSeriesAction::class)->cancel($target->fresh(), 'future');
        $cancelled = RendezVous::query()->where('recurring_series_id', $target->recurring_series_id)->orderBy('series_position')->get();
        $this->assertSame('cancelled', $cancelled[1]->series_status);
        $this->assertSame('refuse', $cancelled[1]->status);
        $this->assertSame('cancelled', $cancelled[2]->series_status);
    }

    protected function createSeries(bool $withAdmin = false): array
    {
        $context = $this->createCoverageContext();
        $client = User::factory()->client()->create();
        $employee = User::factory()->employe()->create();
        $admin = $withAdmin ? User::factory()->admin()->create(['access_scope' => 'all']) : null;
        $startDate = now()->addDays(5)->startOfDay();

        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $startDate->toDateString()]);
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $startDate->copy()->addWeek()->toDateString()]);
        $this->assignEmployeeToZone($employee, $context['zone'], [], ['date' => $startDate->copy()->addWeeks(2)->toDateString()]);

        $seriesId = (string) Str::uuid();
        $records = collect([0, 1, 2])->map(function ($offset) use ($client, $employee, $context, $seriesId, $startDate) {
            return RendezVous::create([
                'client_id' => $client->id,
                'employe_id' => $employee->id,
                'service_catalog_id' => $context['service']->id,
                'service_zone_id' => $context['zone']->id,
                'postal_code_id' => $context['postalCode']->id,
                'booking_reference' => 'SERIE-'.($offset + 1),
                'date' => $startDate->copy()->addWeeks($offset)->toDateString(),
                'heure' => '10:00',
                'status' => 'en_attente',
                'adresse' => 'Rue Test 1',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'type_lieu' => 'appartement',
                'surface' => 'moins_50',
                'frequence' => 'hebdomadaire',
                'is_recurrent' => true,
                'recurrence_rule' => 'weekly',
                'recurring_series_id' => $seriesId,
                'recurrence_frequency' => 'weekly',
                'recurrence_interval' => 1,
                'recurrence_count' => 3,
                'recurrence_days' => [(int) $startDate->isoWeekday()],
                'is_series_master' => $offset === 0,
                'series_position' => $offset + 1,
                'series_status' => 'active',
                'telephone_client' => '0470000000',
                'duree_estimee' => 90,
                'devis_estime' => 79,
                'zone_snapshot' => ['zone' => ['id' => $context['zone']->id]],
                'pricing_snapshot' => [
                    'service_identifier' => $context['service']->code ?: $context['service']->slug,
                    'service_name' => $context['service']->name,
                    'service' => [
                        'id' => $context['service']->id,
                        'service_identifier' => $context['service']->code ?: $context['service']->slug,
                        'code' => $context['service']->code,
                        'slug' => $context['service']->slug,
                        'name' => $context['service']->name,
                    ],
                ],
            ]);
        })->values();

        return [$client, $employee, $records, $admin];
    }
}
