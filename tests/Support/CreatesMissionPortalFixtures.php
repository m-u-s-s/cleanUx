<?php

namespace Tests\Support;

use App\Models\Mission;
use App\Models\MissionVerificationCode;
use App\Models\Booking;
use App\Models\User;

trait CreatesMissionPortalFixtures
{
    use CreatesZoneAwareFixtures;

    protected function createMissionPortalContext(
        array $missionOverrides = [],
        array $rendezVousOverrides = [],
        bool $withStartCode = false,
        bool $withEndCode = false,
    ): array {
        $context = $this->createCoverageContext();

        $client = User::factory()->client()->create([
            'postal_code_id' => $context['postalCode']->id,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        $employee = User::factory()->employe()->create([
            'postal_code_id' => $context['postalCode']->id,
            'primary_service_zone_id' => $context['zone']->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->assignEmployeeToZone(
            $employee,
            $context['zone'],
            [],
            ['date' => now()->addDays(2)->toDateString()]
        );

        $rendezVous = Booking::factory()
            ->forStructuredContext($context['service'], $context['zone'], $context['postalCode'])
            ->create(array_merge([
                'client_id' => $client->id,
                'employe_id' => $employee->id,
                'status' => 'confirme',
                'date' => now()->addDays(2)->toDateString(),
                'heure' => '10:00:00',
                'adresse' => 'Rue de Test 1',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'telephone_client' => '0470000000',
                'type_lieu' => 'appartement',
                'surface' => 'moins_50',
                'frequence' => 'ponctuel',
            ], $rendezVousOverrides));

        // Important: un RendezVous confirmé déclenche déjà la synchronisation de mission
        // via RendezVousObserver. On réutilise donc la mission existante si elle a été créée.
        $mission = $rendezVous->mission()->first();

        if (! $mission) {
            $mission = Mission::factory()->for($rendezVous, 'rendezVous')->create([
                'rendez_vous_id' => $rendezVous->id,
            ]);
        }

        $defaultMissionState = [
            'service_catalog_id' => $rendezVous->service_catalog_id,
            'service_zone_id' => $rendezVous->service_zone_id,
            'organization_account_id' => $rendezVous->organization_account_id,
            'organization_site_id' => $rendezVous->organization_site_id,
            'lead_employee_id' => $employee->id,
            'status' => 'assigned',
            'mission_type' => 'standard',
        ];

        $mission->fill(array_merge($defaultMissionState, $missionOverrides));
        $mission->save();
        $mission->refresh();

        if ($withStartCode) {
            MissionVerificationCode::factory()
                ->startCode()
                ->create([
                    'mission_id' => $mission->id,
                    'is_consumed' => false,
                ]);
        }

        if ($withEndCode) {
            MissionVerificationCode::factory()
                ->endCode()
                ->create([
                    'mission_id' => $mission->id,
                    'is_consumed' => false,
                ]);
        }

        return compact('context', 'client', 'employee', 'rendezVous', 'mission');
    }

    protected function createRecurringPortalContext(): array
    {
        $context = $this->createCoverageContext();

        $client = User::factory()->client()->create([
            'postal_code_id' => $context['postalCode']->id,
            'primary_service_zone_id' => $context['zone']->id,
        ]);

        $employee = User::factory()->employe()->create([
            'postal_code_id' => $context['postalCode']->id,
            'primary_service_zone_id' => $context['zone']->id,
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->assignEmployeeToZone(
            $employee,
            $context['zone'],
            [],
            ['date' => now()->addDays(3)->toDateString()]
        );

        $rendezVous = Booking::factory()
            ->forStructuredContext($context['service'], $context['zone'], $context['postalCode'])
            ->recurringSeries()
            ->create([
                'client_id' => $client->id,
                'employe_id' => $employee->id,
                'status' => 'confirme',
                'date' => now()->addDays(3)->toDateString(),
                'heure' => '11:00:00',
                'adresse' => 'Rue Série 1',
                'ville' => $context['postalCode']->city_name,
                'code_postal' => $context['postalCode']->code,
                'telephone_client' => '0470000000',
            ]);

        return compact('context', 'client', 'employee', 'rendezVous');
    }
}
