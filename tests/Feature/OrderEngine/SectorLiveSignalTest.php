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

/**
 * Le signal vivant des cartes du carrousel.
 *
 * La carte annonçait « 3 métiers » — un fait de catalogue, que le client ne peut ni vérifier ni
 * utiliser. La confiance vient de la disponibilité VISIBLE, pas d'un décompte de rubriques.
 *
 * CE QU'ON NE DIT PAS. Sur cet écran, aucune adresse n'est connue : on ne peut donc rien promettre
 * sur la proximité. Le compte affiché est celui des professionnels actifs du secteur, sans « près
 * de chez vous » — la promesse de distance appartient à l'écran d'adresse, qui la vérifie.
 *
 * La définition d'un professionnel actif est reprise TELLE QUELLE de la preuve de disponibilité.
 * Deux définitions divergentes afficheraient 42 sur la carte et 0 une fois l'adresse saisie.
 */
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

    /**
     * Un professionnel qui exerce DEUX métiers du secteur compte pour un.
     *
     * Le compter deux fois gonflerait la promesse d'autant, et le client s'en apercevrait au
     * premier créneau introuvable.
     */
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

    /**
     * Sans aucun professionnel, on NE DIT PAS « 0 ».
     *
     * Un secteur qu'on annonce vide ne s'ouvre pas — alors qu'il porte peut-être un métier
     * commandable dès demain. On retombe sur le décompte de métiers, qui reste vrai.
     */
    public function test_an_empty_sector_never_advertises_zero(): void
    {
        Livewire::test(OrderJourney::class)
            ->assertDontSee('0 professionnel')
            ->assertSee('métier');
    }

    /**
     * Le compte ne coûte PAS une requête par secteur.
     *
     * C'est le premier écran du produit, celui dont dépend le LCP. Un décompte par carte
     * multiplierait les requêtes par le nombre de secteurs, qui n'a pas de plafond.
     */
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
