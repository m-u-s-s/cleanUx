<?php

namespace Tests\Feature\Regression;

use App\Livewire\Admin\CatalogueServices;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\TradeSeeder;
use Database\Seeders\MultiTradeDemoServicesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tests pour l'intégration multi-métier (Phase 1 — vague mai 2026).
 *
 * Vérifie que :
 *   1. L'admin CatalogueServices accepte trade_id et le persiste
 *   2. Le filtre par trade dans la liste fonctionne
 *   3. La propriété servicesGroupedByTrade groupe correctement
 *   4. Le seeder MultiTradeDemoServicesSeeder crée les services attendus
 *
 * Sans ces tests, n'importe quel refactor ultérieur peut casser le pipeline
 * multi-métier sans alerte.
 */
class MultiTradeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // CatalogueServices admin — trade_id
    // ──────────────────────────────────────────────────────

    public function test_admin_can_save_a_service_with_trade_id(): void
    {
        $this->seed(TradeSeeder::class);
        $painting = Trade::where('slug', 'peinture')->firstOrFail();

        $this->actingAs($this->makeAdmin());

        Livewire::test(CatalogueServices::class)
            ->set('code', 'TEST_PAINT')
            ->set('name', 'Test peinture')
            ->set('slug', 'test-peinture')
            ->set('service_type', 'standard')
            ->set('base_price', 100)
            ->set('default_duration_minutes', 120)
            ->set('trade_id', $painting->id)
            ->call('saveService')
            ->assertHasNoErrors();

        $service = ServiceCatalog::where('slug', 'test-peinture')->first();

        $this->assertNotNull($service,
            "Le service doit être créé."
        );
        $this->assertSame($painting->id, (int) $service->trade_id,
            "trade_id doit être persisté. Vérifier que la propriété est dans "
            . "la validation de saveService() et que ServiceCatalog::\$fillable "
            . "contient 'trade_id'."
        );
    }

    public function test_admin_can_save_a_service_without_trade_id(): void
    {
        // Phase de transition : on accepte trade_id=null pour ne pas casser
        // les imports ou les services historiques.
        $this->actingAs($this->makeAdmin());

        Livewire::test(CatalogueServices::class)
            ->set('code', 'TEST_NOTRADE')
            ->set('name', 'Test sans trade')
            ->set('slug', 'test-sans-trade')
            ->set('service_type', 'standard')
            ->set('base_price', 50)
            ->set('default_duration_minutes', 60)
            ->set('trade_id', null)
            ->call('saveService')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('service_catalogs', [
            'slug' => 'test-sans-trade',
            'trade_id' => null,
        ]);
    }

    public function test_admin_trade_filter_narrows_service_list(): void
    {
        $this->seed(TradeSeeder::class);
        $cleaning = Trade::where('slug', 'nettoyage')->firstOrFail();
        $painting = Trade::where('slug', 'peinture')->firstOrFail();

        ServiceCatalog::create([
            'code' => 'CLEAN_X', 'name' => 'Nettoyage X', 'slug' => 'nettoyage-x',
            'service_type' => 'standard', 'is_active' => true,
            'default_duration_minutes' => 60, 'base_price' => 0,
            'trade_id' => $cleaning->id,
        ]);
        ServiceCatalog::create([
            'code' => 'PAINT_Y', 'name' => 'Peinture Y', 'slug' => 'peinture-y',
            'service_type' => 'standard', 'is_active' => true,
            'default_duration_minutes' => 60, 'base_price' => 0,
            'trade_id' => $painting->id,
        ]);

        $this->actingAs($this->makeAdmin());

        // Avec filtre Peinture → seul "Peinture Y" doit apparaître
        Livewire::test(CatalogueServices::class)
            ->set('tradeFilter', $painting->id)
            ->assertSee('Peinture Y')
            ->assertDontSee('Nettoyage X');

        // Avec filtre Nettoyage → seul "Nettoyage X" doit apparaître
        Livewire::test(CatalogueServices::class)
            ->set('tradeFilter', $cleaning->id)
            ->assertSee('Nettoyage X')
            ->assertDontSee('Peinture Y');
    }

    // ──────────────────────────────────────────────────────
    // Booking flow — services groupés par trade
    // ──────────────────────────────────────────────────────

    /**
     * Vérifie que `servicesGroupedByTrade` retourne bien la structure
     * attendue par le <optgroup> de la vue field-service.blade.php.
     *
     * On instancie un objet anonyme qui utilise le trait pour pouvoir
     * tester sans monter tout le composant Livewire.
     */
    public function test_services_grouped_by_trade_returns_proper_structure(): void
    {
        $this->seed(TradeSeeder::class);
        $cleaning = Trade::where('slug', 'nettoyage')->firstOrFail();
        $painting = Trade::where('slug', 'peinture')->firstOrFail();

        ServiceCatalog::create([
            'code' => 'A', 'name' => 'Nettoyage A', 'slug' => 'nettoyage-a',
            'service_type' => 'standard', 'is_active' => true,
            'default_duration_minutes' => 60, 'base_price' => 0,
            'trade_id' => $cleaning->id, 'sort_order' => 1,
        ]);
        ServiceCatalog::create([
            'code' => 'B', 'name' => 'Peinture B', 'slug' => 'peinture-b',
            'service_type' => 'standard', 'is_active' => true,
            'default_duration_minutes' => 60, 'base_price' => 0,
            'trade_id' => $painting->id, 'sort_order' => 1,
        ]);
        ServiceCatalog::create([
            'code' => 'C', 'name' => 'Service orphelin', 'slug' => 'service-orphelin',
            'service_type' => 'standard', 'is_active' => true,
            'default_duration_minutes' => 60, 'base_price' => 0,
            'trade_id' => null, 'sort_order' => 99,
        ]);

        // Instancier le composant Livewire de booking pour exercer le trait
        // qui définit getServicesGroupedByTradeProperty.
        $component = new class extends \Livewire\Component {
            use \App\Support\Livewire\Concerns\InteractsWithBookingFormState;
            public function render() { return ''; }
        };

        $grouped = $component->servicesGroupedByTrade;

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('Nettoyage', $grouped);
        $this->assertArrayHasKey('Peinture', $grouped);
        $this->assertArrayHasKey('Autres', $grouped,
            "Les services sans trade doivent être groupés sous 'Autres' "
            . "pour ne pas être perdus pendant la transition multi-métiers."
        );
        $this->assertSame('Nettoyage A', $grouped['Nettoyage']['A'] ?? null);
        $this->assertSame('Peinture B', $grouped['Peinture']['B'] ?? null);
        $this->assertSame('Service orphelin', $grouped['Autres']['C'] ?? null);
    }

    // ──────────────────────────────────────────────────────
    // Seeder — services demo multi-trade
    // ──────────────────────────────────────────────────────

    public function test_demo_services_seeder_creates_services_for_each_non_cleaning_trade(): void
    {
        $this->seed(TradeSeeder::class);
        $this->seed(MultiTradeDemoServicesSeeder::class);

        foreach (['peinture', 'batiment', 'levage', 'jardinage'] as $slug) {
            $trade = Trade::where('slug', $slug)->firstOrFail();
            $count = ServiceCatalog::where('trade_id', $trade->id)->count();
            $this->assertGreaterThan(0, $count,
                "Le trade '{$slug}' doit avoir au moins 1 service de démo. "
                . "Vérifier database/seeders/MultiTradeDemoServicesSeeder.php."
            );
        }
    }

    public function test_demo_services_seeder_is_idempotent(): void
    {
        $this->seed(TradeSeeder::class);

        $this->seed(MultiTradeDemoServicesSeeder::class);
        $countAfterFirst = ServiceCatalog::count();

        $this->seed(MultiTradeDemoServicesSeeder::class);
        $countAfterSecond = ServiceCatalog::count();

        $this->assertSame($countAfterFirst, $countAfterSecond,
            "Le seeder doit être idempotent (updateOrCreate sur slug). "
            . "Re-seeder ne doit PAS dupliquer les services."
        );
    }
}
