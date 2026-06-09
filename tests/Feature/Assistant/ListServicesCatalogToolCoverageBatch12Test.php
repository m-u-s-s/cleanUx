<?php

namespace Tests\Feature\Assistant;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use App\Services\Assistant\Tools\Implementations\ListServicesCatalogTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListServicesCatalogToolCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    private function tool(): ListServicesCatalogTool
    {
        return new ListServicesCatalogTool;
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'client']);
    }

    public function test_metadata_schema_and_flags(): void
    {
        $tool = $this->tool();

        $this->assertSame('list_services_catalog', $tool->name());
        $this->assertStringContainsString('service', strtolower($tool->description()));
        $this->assertTrue($tool->executesImmediately());
        $this->assertTrue($tool->authorize($this->actor()));

        $schema = $tool->inputSchema();
        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('trade_slug', $schema['properties']);
        $this->assertArrayHasKey('is_b2b', $schema['properties']);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertSame([], $schema['required']);
    }

    public function test_execute_returns_active_services_ordered(): void
    {
        $trade = Trade::factory()->create(['slug' => 'nettoyage']);

        $active = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'name' => 'Alpha Service',
            'is_active' => true,
            'sort_order' => 1,
            'base_price' => 99.50,
        ]);

        ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $result = $this->tool()->execute($this->actor(), []);

        $this->assertSame(1, $result['count']);
        $this->assertCount(1, $result['services']);

        $row = $result['services'][0];
        $this->assertSame($active->id, $row['id']);
        $this->assertSame('Alpha Service', $row['name']);
        $this->assertSame('nettoyage', $row['trade_slug']);
        $this->assertSame(99.50, $row['base_price']);
        $this->assertSame('EUR', $row['currency']);
        $this->assertIsBool($row['requires_quote']);
        $this->assertIsBool($row['requires_site_visit']);
    }

    public function test_execute_filters_by_trade_slug(): void
    {
        $cleaning = Trade::factory()->create(['slug' => 'nettoyage']);
        $painting = Trade::factory()->create(['slug' => 'peinture']);

        $kept = ServiceCatalog::factory()->create([
            'trade_id' => $cleaning->id,
            'is_active' => true,
        ]);
        ServiceCatalog::factory()->create([
            'trade_id' => $painting->id,
            'is_active' => true,
        ]);

        $result = $this->tool()->execute($this->actor(), ['trade_slug' => 'nettoyage']);

        $this->assertSame(1, $result['count']);
        $this->assertSame($kept->id, $result['services'][0]['id']);
    }

    public function test_unknown_trade_slug_is_ignored(): void
    {
        $trade = Trade::factory()->create(['slug' => 'nettoyage']);
        ServiceCatalog::factory()->count(2)->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        $result = $this->tool()->execute($this->actor(), ['trade_slug' => 'does-not-exist']);

        // No matching trade -> filter not applied, all active returned.
        $this->assertSame(2, $result['count']);
    }

    public function test_execute_filters_by_b2b_flag(): void
    {
        $trade = Trade::factory()->create();

        $b2b = ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
            'is_b2b_available' => true,
            'is_personal_available' => false,
        ]);
        ServiceCatalog::factory()->create([
            'trade_id' => $trade->id,
            'is_active' => true,
            'is_b2b_available' => false,
            'is_personal_available' => true,
        ]);

        $b2bResult = $this->tool()->execute($this->actor(), ['is_b2b' => true]);
        $this->assertSame(1, $b2bResult['count']);
        $this->assertSame($b2b->id, $b2bResult['services'][0]['id']);

        $b2cResult = $this->tool()->execute($this->actor(), ['is_b2b' => false]);
        $this->assertSame(1, $b2cResult['count']);
        $this->assertNotSame($b2b->id, $b2cResult['services'][0]['id']);
    }

    public function test_limit_is_capped_at_thirty(): void
    {
        $trade = Trade::factory()->create();
        ServiceCatalog::factory()->count(3)->create([
            'trade_id' => $trade->id,
            'is_active' => true,
        ]);

        $result = $this->tool()->execute($this->actor(), ['limit' => 2]);
        $this->assertSame(2, $result['count']);

        // Oversized limit is clamped to 30; with only 3 rows it returns 3.
        $capped = $this->tool()->execute($this->actor(), ['limit' => 999]);
        $this->assertSame(3, $capped['count']);
    }
}
