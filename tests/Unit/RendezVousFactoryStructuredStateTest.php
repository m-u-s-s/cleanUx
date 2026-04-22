<?php

namespace Tests\Unit;

use App\Models\RendezVous;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RendezVousFactoryStructuredStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_factory_creates_structured_references_and_snapshots(): void
    {
        $rdv = RendezVous::factory()->create();

        $this->assertNotNull($rdv->service_catalog_id);
        $this->assertNotNull($rdv->service_zone_id);
        $this->assertNotNull($rdv->postal_code_id);
        $this->assertNotNull($rdv->booking_reference);
        $this->assertIsArray($rdv->zone_snapshot);
        $this->assertIsArray($rdv->pricing_snapshot);
        $this->assertSame($rdv->postalCode?->code, $rdv->code_postal);
        $this->assertSame($rdv->postalCode?->city_name, $rdv->ville);
        $this->assertNull($rdv->getRawOriginal('service_type'));
        $this->assertSame(
            $rdv->serviceCatalog?->code ?: $rdv->serviceCatalog?->slug,
            data_get($rdv->pricing_snapshot, 'service_identifier')
        );
        $this->assertSame(
            $rdv->serviceCatalog?->code ?: $rdv->serviceCatalog?->slug,
            data_get($rdv->pricing_snapshot, 'service.service_identifier')
        );
    }

    public function test_entreprise_state_creates_coherent_corporate_context(): void
    {
        $rdv = RendezVous::factory()->entreprise()->create();

        $this->assertNotNull($rdv->organization_account_id);
        $this->assertNotNull($rdv->organization_site_id);
        $this->assertSame('entreprise_portal', $rdv->booking_channel);
        $this->assertTrue((bool) data_get($rdv->pricing_snapshot, 'service.is_entreprise'));
        $this->assertSame('entreprise', data_get($rdv->pricing_snapshot, 'corporate_context.market'));
        $this->assertSame(
            $rdv->serviceCatalog?->code ?: $rdv->serviceCatalog?->slug,
            data_get($rdv->pricing_snapshot, 'service_identifier')
        );
    }
}
