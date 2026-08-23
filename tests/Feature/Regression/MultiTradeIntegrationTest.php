<?php

namespace Tests\Feature\Regression;

use App\Livewire\Admin\CatalogueServices;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\MultiTradeDemoServicesSeeder;
use Database\Seeders\TradeSeeder;
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
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
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
            'Le service doit être créé.'
        );
        $this->assertSame($painting->id, (int) $service->trade_id,
            'trade_id doit être persisté. Vérifier que la propriété est dans '
            .'la validation de saveService() et que ServiceCatalog::$fillable '
            ."contient 'trade_id'."
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
    // Seeder — services demo multi-trade
    // ──────────────────────────────────────────────────────

    public function test_demo_services_seeder_creates_services_for_each_non_cleaning_trade(): void
    {
        $this->seed(TradeSeeder::class);
        $this->seed(MultiTradeDemoServicesSeeder::class);

        // Les quatre métiers relevés ensemble : un semeur incomplet en laisse plusieurs sans
        // service, et chacun est un métier qu'aucun client ne peut commander.
        $sansService = [];

        foreach (['peinture', 'batiment', 'levage', 'jardinage'] as $slug) {
            $trade = Trade::where('slug', $slug)->firstOrFail();

            if (ServiceCatalog::where('trade_id', $trade->id)->count() === 0) {
                $sansService[] = $slug;
            }
        }

        $this->assertSame(
            [],
            $sansService,
            'Ces métiers n’ont aucun service de démonstration. '
            .'Vérifier database/seeders/MultiTradeDemoServicesSeeder.php.',
        );
    }

    public function test_demo_services_seeder_is_idempotent(): void
    {
        $this->seed(TradeSeeder::class);

        $this->seed(MultiTradeDemoServicesSeeder::class);
        $countAfterFirst = ServiceCatalog::count();

        $this->seed(MultiTradeDemoServicesSeeder::class);
        $countAfterSecond = ServiceCatalog::count();

        $this->assertSame($countAfterFirst, $countAfterSecond,
            'Le seeder doit être idempotent (updateOrCreate sur slug). '
            .'Re-seeder ne doit PAS dupliquer les services.'
        );
    }
}
