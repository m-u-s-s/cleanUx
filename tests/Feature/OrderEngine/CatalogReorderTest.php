<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'ordre du catalogue se règle à la souris, et pas seulement pour les secteurs.
 *
 * L'ordre n'est pas cosmétique : c'est celui du carrousel et celui du dock, donc le premier métier
 * que voit un client. Le régler tenait à une paire de flèches sur les secteurs — rien du tout sur
 * les métiers, alors que ce sont eux qui se vendent.
 *
 * Les flèches RESTENT. Le glisser-déposer ne fonctionne ni au clavier ni avec un lecteur d'écran,
 * et le catalogue est un écran de travail quotidien.
 */
class CatalogReorderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'platform_role' => 'admin']));
    }

    public function test_sectors_can_be_reordered_by_dragging(): void
    {
        $ids = Sector::ordered()->pluck('id')->all();
        $shuffled = array_reverse($ids);

        Livewire::test(CatalogCenter::class)->call('reorderSectors', $shuffled);

        $this->assertSame($shuffled, Sector::ordered()->pluck('id')->all());
    }

    public function test_trades_can_be_reordered_inside_their_sector(): void
    {
        $sector = $this->batiment();
        $ids = $sector->trades()->orderBy('sort_order')->pluck('id')->all();

        $this->assertGreaterThan(1, count($ids), 'Le secteur testé doit porter plusieurs métiers.');

        $shuffled = array_reverse($ids);

        Livewire::test(CatalogCenter::class)->call('reorderTrades', $sector->id, $shuffled);

        $this->assertSame(
            $shuffled,
            $sector->trades()->orderBy('sort_order')->pluck('id')->all(),
        );
    }

    /** Les flèches restent : le glisser-déposer n'existe ni au clavier ni au lecteur d'écran. */
    public function test_a_trade_moves_with_the_arrows_too(): void
    {
        $sector = $this->batiment();
        $ids = $sector->trades()->orderBy('sort_order')->pluck('id')->all();

        Livewire::test(CatalogCenter::class)->call('moveTrade', $ids[0], 1);

        $after = $sector->trades()->orderBy('sort_order')->pluck('id')->all();

        $this->assertSame($ids[1], $after[0]);
        $this->assertSame($ids[0], $after[1]);
    }

    /**
     * Une liste PARTIELLE est refusée.
     *
     * L'ordre vient du navigateur ; accepter une liste incomplète laisserait des métiers sans rang
     * défini, donc à une place arbitraire au prochain affichage. On refuse plutôt que de
     * réordonner à moitié — même règle que le constructeur de questions.
     */
    public function test_a_partial_list_is_refused_rather_than_half_applied(): void
    {
        $sector = $this->batiment();
        $before = $sector->trades()->orderBy('sort_order')->pluck('id')->all();

        Livewire::test(CatalogCenter::class)->call('reorderTrades', $sector->id, [$before[0]]);

        $this->assertSame($before, $sector->trades()->orderBy('sort_order')->pluck('id')->all());
    }

    /**
     * Un métier d'un AUTRE secteur ne se glisse pas ici.
     *
     * L'ordre arrive du navigateur : il n'est pas cru sur parole. Sans ce contrôle, une liste
     * forgée réordonnerait un secteur voisin.
     */
    public function test_a_trade_from_another_sector_is_ignored(): void
    {
        $sector = $this->batiment();
        $intruder = Trade::whereNotNull('sector_id')->where('sector_id', '!=', $sector->id)->firstOrFail();
        $before = $sector->trades()->orderBy('sort_order')->pluck('id')->all();

        Livewire::test(CatalogCenter::class)
            ->call('reorderTrades', $sector->id, array_merge($before, [$intruder->id]));

        $this->assertSame($before, $sector->trades()->orderBy('sort_order')->pluck('id')->all());
        $this->assertSame($intruder->sector_id, $intruder->fresh()->sector_id);
    }

    /** Le lecteur seul ne réordonne pas le catalogue. */
    public function test_a_read_only_admin_cannot_reorder(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]));

        $before = Sector::ordered()->pluck('id')->all();

        Livewire::test(CatalogCenter::class)->call('reorderSectors', array_reverse($before));

        $this->assertSame($before, Sector::ordered()->pluck('id')->all());
    }

    /**
     * L'écran câble ce qu'il annonce — et les flèches survivent au glisser-déposer.
     *
     * Septième défaut de ce module : un service vert sans porte d'entrée. Et le glisser-déposer
     * seul exclurait du catalogue quiconque travaille au clavier.
     */
    public function test_the_screen_offers_both_ways(): void
    {
        $html = Livewire::test(CatalogCenter::class)->html();

        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString('reorderSectors', $html);
        $this->assertStringContainsString('reorderTrades', $html);
        $this->assertStringContainsString('moveTrade', $html);
        $this->assertStringContainsString('aria-label="Monter"', $html);
        $this->assertStringContainsString('aria-label="Descendre"', $html);
    }

    private function batiment(): Sector
    {
        return Sector::where('slug', 'batiment-renovation')->firstOrFail();
    }
}
