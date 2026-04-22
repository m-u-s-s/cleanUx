<?php

namespace Tests\Unit;

use App\Models\PostalCode;
use App\Models\RendezVous;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Integrations\GoogleCalendarOAuthService;
use App\Services\Integrations\GoogleCalendarSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarSyncServicePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_calendar_payload_prefers_structured_service_and_location_labels(): void
    {
        $employee = User::factory()->employe()->create();
        $client = User::factory()->client()->create(['name' => 'Client Agenda']);
        $service = ServiceCatalog::factory()->create([
            'name' => 'Nettoyage vitres premium',
            'code' => 'vitres-premium',
            'service_type' => 'nettoyage_vitres',
        ]);
        $zone = ServiceZone::factory()->create(['name' => 'Bruxelles Centre']);
        $postalCode = PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles']);

        $rdv = RendezVous::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $employee->id,
            'service_catalog_id' => $service->id,
            'service_zone_id' => $zone->id,
            'postal_code_id' => $postalCode->id,
            'adresse' => 'Rue du Test 10',
            'pricing_snapshot' => [
                'service_identifier' => 'vitres-premium',
                'service_name' => 'Nettoyage vitres premium',
                'service' => [
                    'service_identifier' => 'vitres-premium',
                    'code' => 'vitres-premium',
                    'name' => 'Nettoyage vitres premium',
                ],
            ],
            'code_postal' => null,
            'ville' => 'Bruxelles',
            'date' => '2026-04-15',
            'heure' => '09:30:00',
        ]);

        $serviceInstance = new GoogleCalendarSyncService($this->createMock(GoogleCalendarOAuthService::class));
        $method = new \ReflectionMethod($serviceInstance, 'buildGoogleEventPayload');
        $method->setAccessible(true);

        $payload = $method->invoke(
            $serviceInstance,
            $rdv->fresh(['serviceCatalog', 'serviceZone', 'postalCode', 'client']),
            Carbon::parse('2026-04-15 09:30:00', 'Europe/Brussels'),
            Carbon::parse('2026-04-15 11:30:00', 'Europe/Brussels')
        );

        $this->assertSame('CleanUx · Nettoyage Vitres Premium · Bruxelles Centre', $payload['summary']);
        $this->assertSame('Rue du Test 10, 1000, Bruxelles', $payload['location']);
        $this->assertStringContainsString('Service : Nettoyage Vitres Premium', $payload['description']);
        $this->assertSame((string) $service->id, $payload['extendedProperties']['private']['cleanux_service_catalog_id']);
        $this->assertSame('vitres-premium', $payload['extendedProperties']['private']['cleanux_service_identifier']);
    }
}
