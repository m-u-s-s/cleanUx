<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\BrowseProviders;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La page des favoris employes est supprimee : sa raison d'etre — retrouver ses preferes —
 * vit desormais dans l'annuaire, comme un filtre de plus.
 */
class L_AnnuaireFiltreMesPreferesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 200)]);
    }

    private function prestataire(string $nom): User
    {
        $employe = User::factory()->employe()->create(['name' => $nom]);

        ProviderProfile::create([
            'user_id' => $employe->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        $metier = Trade::query()->first() ?? Trade::factory()->create(['is_active' => true]);
        $employe->trades()->syncWithoutDetaching([$metier->id]);

        return $employe->fresh();
    }

    public function test_le_filtre_ne_garde_que_mes_preferes(): void
    {
        $prefere = $this->prestataire('Amandine Prefere');
        $autre = $this->prestataire('Bertrand Inconnu');

        $client = User::factory()->premiumClient()->create();
        $client->favoriteEmployes()->attach($prefere->id, ['is_favorite' => true]);

        Livewire::actingAs($client)->test(BrowseProviders::class)
            ->set('seulementPreferes', true)
            ->assertSee($prefere->name)
            ->assertDontSee($autre->name);
    }

    /**
     * TEMOIN — sans le filtre, les DEUX sont la. Sinon le test ci-dessus resterait vert si
     * l'annuaire ne rendait plus qu'une seule carte, ou aucune.
     */
    public function test_temoin_sans_le_filtre_tout_l_annuaire_est_la(): void
    {
        $prefere = $this->prestataire('Amandine Prefere');
        $autre = $this->prestataire('Bertrand Inconnu');

        $client = User::factory()->premiumClient()->create();
        $client->favoriteEmployes()->attach($prefere->id, ['is_favorite' => true]);

        Livewire::actingAs($client)->test(BrowseProviders::class)
            ->assertSee($prefere->name)
            ->assertSee($autre->name);
    }

    /** Un client standard n'a pas de preferes : le filtre ne lui est pas propose. */
    public function test_le_filtre_n_est_pas_offert_au_client_standard(): void
    {
        $this->prestataire('Amandine Prefere');

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->assertDontSee('Mes préférés');
    }

    /** Force par le navigateur, le filtre reste sans effet pour un standard. */
    public function test_force_le_filtre_ne_vide_pas_l_annuaire_d_un_standard(): void
    {
        $employe = $this->prestataire('Amandine Prefere');

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->set('seulementPreferes', true)
            ->assertSee($employe->name);
    }

    /** Le client standard voit l'appel a l'offre, repris de la page supprimee. */
    public function test_le_bloc_premium_de_page_accueille_le_client_standard(): void
    {
        $this->prestataire('Amandine Prefere');

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->assertSee('Choisissez vos prestataires')
            ->assertSee(route('premium.offer'), escape: false);
    }
}
