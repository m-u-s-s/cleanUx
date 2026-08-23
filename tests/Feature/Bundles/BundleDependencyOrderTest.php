<?php

namespace Tests\Feature\Bundles;

use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bundle Marketplace P5 — ordonnancement des interventions : à l'acceptation, les bookings reçoivent un ordre d'exécution respectant depends_on (ex: carrelage avant peinture), même si l'ordre de saisie diffère. */
class BundleDependencyOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_stamps_execution_order_respecting_dependencies(): void
    {
        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();

        $bundle = app(MultiTradeBundleService::class)->createDraft($client, 'Rénovation', [
            ['trade_id' => $trade->id, 'label' => 'Peinture', 'estimated_price_cents' => 2000],
            ['trade_id' => $trade->id, 'label' => 'Carrelage', 'estimated_price_cents' => 5000],
        ]);

        $painting = $bundle->items[0]; // sequence_order 0
        $tiling = $bundle->items[1];   // sequence_order 1

        // La peinture (saisie en 1er) DÉPEND du carrelage → doit passer APRÈS lui.
        $painting->update([
            'assigned_provider_user_id' => User::factory()->employe()->create()->id,
            'quoted_price_cents' => 2000,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
            'depends_on_item_ids' => [$tiling->id],
        ]);
        $tiling->update([
            'assigned_provider_user_id' => User::factory()->employe()->create()->id,
            'quoted_price_cents' => 5000,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
        ]);

        app(MultiTradeBundleService::class)->accept($bundle->fresh('items'));

        $tilingOrder = (int) data_get($tiling->fresh()->booking->matching_snapshot, 'execution_order');
        $paintingOrder = (int) data_get($painting->fresh()->booking->matching_snapshot, 'execution_order');

        $this->assertSame(1, $tilingOrder, 'Le carrelage (prérequis) doit être exécuté en premier.');
        $this->assertSame(2, $paintingOrder, 'La peinture (dépendante) doit passer après, malgré son sequence_order plus petit.');
    }
}
