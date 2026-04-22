<?php

namespace Tests\Unit;

use App\Services\Booking\ZoneCoverageService;
use Database\Seeders\BelgiumGeographySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneCoverageServiceBelgiumReferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_bilingual_and_normalized_belgian_city_names(): void
    {
        $this->seed(BelgiumGeographySeeder::class);

        $service = app(ZoneCoverageService::class);

        $brussels = $service->resolvePostalCode('1000', 'Brussel');
        $this->assertNotNull($brussels);
        $this->assertSame('1000', $brussels->code);
        $this->assertSame('Bruxelles', $brussels->commune?->name);

        $ghent = $service->resolvePostalCode('9000', 'Ghent');
        $this->assertNotNull($ghent);
        $this->assertSame('9000', $ghent->code);
        $this->assertSame('Gand', $ghent->commune?->name);

        $liege = $service->resolvePostalCode('4000', 'Liege');
        $this->assertNotNull($liege);
        $this->assertSame('4000', $liege->code);
        $this->assertSame('Liège', $liege->commune?->name);

        $nivelles = $service->resolvePostalCode('1400', 'Nijvel');
        $this->assertNotNull($nivelles);
        $this->assertSame('1400', $nivelles->code);
        $this->assertSame('Nivelles', $nivelles->commune?->name);
    }

    public function test_it_prefers_primary_reference_when_only_postal_code_is_given(): void
    {
        $this->seed(BelgiumGeographySeeder::class);

        $postalCode = app(ZoneCoverageService::class)->resolvePostalCode('1000');

        $this->assertNotNull($postalCode);
        $this->assertSame('Bruxelles', $postalCode->city_name);
        $this->assertSame('Bruxelles', $postalCode->commune?->name);
    }
}
