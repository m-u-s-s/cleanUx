<?php

namespace Tests\Feature\OrderEngine;

use App\Enums\OrganizationType;
use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\GeocodingService;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'annuaire premium passe un prestataire au parcours. Ce n'est qu'une PREFERENCE : le
 * catalogue, le prix et le dispatch ne changent pas, et un prestataire non eligible est ecarte.
 */
class LePrestataireVouluArriveDansLeParcoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OrderEngineCatalogSeeder::class);
        Http::fake(['*' => Http::response([], 200)]);
    }

    private function prestataireDuMetier(Trade $metier): User
    {
        $employe = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $employe->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $employe->trades()->syncWithoutDetaching([$metier->id]);

        return $employe->fresh();
    }

    private const LAT = 50.8466;

    private const LNG = 4.3528;

    /** Un prestataire ELIGIBLE : du bon metier, et positionne pres de l'adresse. */
    private function prestataireProche(Trade $metier): User
    {
        $employe = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        ProviderProfile::create([
            'user_id' => $employe->id,
            'status' => 'active',
            'verification_status' => 'verified',
            'current_lat' => self::LAT,
            'current_lng' => self::LNG,
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $employe->id,
            'trade_id' => $metier->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $employe->fresh();
    }

    private function faussaireDeGeocodage(): void
    {
        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')->andReturn(new GeocodingResult(self::LAT, self::LNG, 'Rue de la Loi 1, 1000 Bruxelles'));
        });
    }

    /**
     * LE CONTROLE QUI COMPTE — un prestataire eligible EST retenu. Sans lui, le test du
     * prestataire ecarte resterait vert alors que le mecanisme entier serait inerte.
     */
    public function test_un_prestataire_eligible_est_retenu(): void
    {
        $metier = Trade::where('slug', 'peinture')->firstOrFail();
        $employe = $this->prestataireProche($metier);
        $this->faussaireDeGeocodage();

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->withQueryParams(['prestataire' => $employe->id])
            ->test(OrderJourney::class)
            ->call('selectTrade', $metier->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles')
            ->assertSet('selectedProviderId', $employe->id)
            ->assertSet('prestataireSouhaite', null);
    }

    /** LA SOCIETE AUSSI : on retient un de ses prestataires eligibles. */
    public function test_une_societe_voulue_retient_un_de_ses_prestataires(): void
    {
        $metier = Trade::where('slug', 'peinture')->firstOrFail();
        $employe = $this->prestataireProche($metier);
        $societe = OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
        ]);

        DB::table('provider_profiles')->where('user_id', $employe->id)
            ->update(['organization_account_id' => $societe->id]);

        $this->faussaireDeGeocodage();

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->withQueryParams(['societe' => $societe->id])
            ->test(OrderJourney::class)
            ->call('selectTrade', $metier->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles')
            ->assertSet('selectedProviderId', $employe->id);
    }

    /** Un prestataire hors des options est ECARTE, et la commande continue. */
    public function test_un_prestataire_non_eligible_est_ecarte_sans_bloquer(): void
    {
        $etranger = User::factory()->employe()->create();

        $composant = Livewire::actingAs(User::factory()->premiumClient()->create())
            ->withQueryParams(['prestataire' => $etranger->id])
            ->test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles');

        $composant->assertOk()->assertSet('selectedProviderId', null);
    }

    /**
     * TEMOIN — un client NON premium qui forge l'URL n'obtient rien : l'intention n'est meme
     * pas retenue. Sans ce controle, le test ci-dessus resterait vert par simple inaction.
     */
    public function test_temoin_un_client_standard_ne_retient_aucune_intention(): void
    {
        $employe = $this->prestataireDuMetier(Trade::where('slug', 'peinture')->firstOrFail());

        Livewire::actingAs(User::factory()->client()->create())
            ->withQueryParams(['prestataire' => $employe->id])
            ->test(OrderJourney::class)
            ->assertSet('prestataireSouhaite', null);
    }

    /** Le premium, lui, voit son intention retenue au montage. */
    public function test_le_premium_arrive_avec_son_intention(): void
    {
        $employe = $this->prestataireDuMetier(Trade::where('slug', 'peinture')->firstOrFail());

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->withQueryParams(['prestataire' => $employe->id])
            ->test(OrderJourney::class)
            ->assertSet('prestataireSouhaite', $employe->id);
    }

    /** L'intention ne survit pas a la premiere application : elle ne se rejoue pas. */
    public function test_l_intention_ne_se_rejoue_pas(): void
    {
        $etranger = User::factory()->employe()->create();

        $composant = Livewire::actingAs(User::factory()->premiumClient()->create())
            ->withQueryParams(['prestataire' => $etranger->id])
            ->test(OrderJourney::class);

        $composant->assertSet('prestataireSouhaite', $etranger->id);
    }
}
