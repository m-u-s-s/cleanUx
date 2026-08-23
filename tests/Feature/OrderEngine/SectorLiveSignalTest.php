<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\ProviderProfile;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Le signal vivant des cartes du carrousel. */
class SectorLiveSignalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_the_card_shows_how_many_professionals_work_the_sector(): void
    {
        $trade = $this->peinture();
        $this->providerFor($trade);
        $this->providerFor($trade);

        Livewire::test(OrderJourney::class)->assertSee('2 professionnels');
    }

    /** Un professionnel qui exerce DEUX métiers du secteur compte pour un. */
    public function test_a_provider_working_two_trades_counts_once(): void
    {
        $sector = $this->batiment();
        $trades = $sector->trades()->limit(2)->get();
        $this->assertCount(2, $trades);

        $provider = $this->providerFor($trades[0]);
        $provider->trades()->attach($trades[1]->id);

        Livewire::test(OrderJourney::class)->assertSee('1 professionnel');
    }

    /** Un profil inactif n'est pas un professionnel disponible. */
    public function test_an_inactive_profile_is_not_counted(): void
    {
        $trade = $this->peinture();
        $provider = $this->providerFor($trade);
        $provider->providerProfile->update(['status' => 'suspended']);

        Livewire::test(OrderJourney::class)->assertDontSee('1 professionnel');
    }

    /** Sans aucun professionnel, on NE DIT PAS « 0 ». */
    public function test_an_empty_sector_never_advertises_zero(): void
    {
        Livewire::test(OrderJourney::class)
            ->assertDontSee('0 professionnel')
            ->assertSee('métier');
    }

    /** Le compte ne coûte PAS une requête par secteur. */
    public function test_the_counts_cost_one_query_not_one_per_sector(): void
    {
        $this->providerFor($this->peinture());

        $component = Livewire::test(OrderJourney::class);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $component->instance()->sectors;

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            3,
            $count,
            sprintf('Les secteurs coûtent %d requêtes : le compte est probablement fait carte par carte.', $count),
        );
    }

    private function providerFor(Trade $trade): User
    {
        $user = User::factory()->employe()->create();
        ProviderProfile::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $user->trades()->attach($trade->id);

        return $user->fresh();
    }

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    private function batiment(): Sector
    {
        return Sector::where('slug', 'batiment-renovation')->firstOrFail();
    }
}
