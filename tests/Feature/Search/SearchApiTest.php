<?php

namespace Tests\Feature\Search;

use App\Models\PostalCode;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_providers_search_returns_paginated_payload(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create(['role' => 'employe']);
            ProviderProfile::create([
                'user_id' => $user->id,
                'provider_type' => 'independent',
                'status' => 'active',
                'verification_status' => 'verified',
                'rating_avg' => 4.5,
                'rating_count' => 10,
                'hourly_rate' => 40 + $i * 10,
            ]);
        }

        $response = $this->getJson('/api/search/providers');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'rating', 'hourly_rate', 'is_online', 'profile_url'],
            ],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
        ]);
        $this->assertSame(3, $response->json('meta.total'));
    }

    /**
     * UN MÉTIER ÉCRIT EN TOUTES LETTRES DOIT FILTRER.
     *
     * L'écran « Explorer » envoie `trade=nettoyage` ; le contrôleur ne déclarait que `trade_id`, si
     * bien que `validate()` écartait le paramètre en silence et rendait l'annuaire ENTIER. Mesuré
     * en direct avant correction : `?trade=peinture` et `?trade=nettoyage` renvoyaient le même
     * prestataire de nettoyage.
     */
    public function test_providers_search_filtre_sur_un_metier_ecrit_en_toutes_lettres(): void
    {
        $nettoyage = Trade::create(['name' => 'Nettoyage à domicile', 'code' => 'CLN-T', 'slug' => 'nettoyage']);
        $peinture = Trade::create(['name' => 'Peinture', 'code' => 'PNT-T', 'slug' => 'peinture']);

        $laveur = $this->prestataireAvecMetier($nettoyage, 'Laveur de vitres');
        $this->prestataireAvecMetier($peinture, 'Peintre en bâtiment');

        $reponse = $this->getJson('/api/search/providers?trade=nettoyage');

        $reponse->assertOk();
        $this->assertSame(1, $reponse->json('meta.total'));
        $this->assertSame($laveur->id, $reponse->json('data.0.id'));
    }

    /**
     * TÉMOIN POSITIF : sans filtre, les DEUX prestataires ressortent.
     *
     * Sans lui, le test ci-dessus passerait au vert même si le filtre excluait tout le monde —
     * ce qui a failli arriver : une première version résolvait « nettoyage » vers le mauvais
     * métier et ne rendait plus personne.
     */
    public function test_providers_search_sans_metier_rend_tout_le_monde(): void
    {
        $nettoyage = Trade::create(['name' => 'Nettoyage à domicile', 'code' => 'CLN-T', 'slug' => 'nettoyage']);
        $peinture = Trade::create(['name' => 'Peinture', 'code' => 'PNT-T', 'slug' => 'peinture']);

        $this->prestataireAvecMetier($nettoyage, 'Laveur de vitres');
        $this->prestataireAvecMetier($peinture, 'Peintre en bâtiment');

        $this->getJson('/api/search/providers')->assertOk()->assertJsonPath('meta.total', 2);
    }

    /** Un libellé qui ne correspond à aucun métier rend zéro résultat, et non l'annuaire entier. */
    public function test_providers_search_un_metier_inconnu_ne_rend_personne(): void
    {
        $nettoyage = Trade::create(['name' => 'Nettoyage à domicile', 'code' => 'CLN-T', 'slug' => 'nettoyage']);
        $this->prestataireAvecMetier($nettoyage, 'Laveur de vitres');

        $this->getJson('/api/search/providers?trade=zzzzz')->assertOk()->assertJsonPath('meta.total', 0);
    }

    private function prestataireAvecMetier(Trade $trade, string $nom): User
    {
        $user = User::factory()->create(['role' => 'employe', 'name' => $nom]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 4.5,
            'rating_count' => 3,
            'hourly_rate' => 40,
        ]);

        $user->trades()->attach($trade->id);

        return $user;
    }

    public function test_providers_search_min_rating_filter_via_api(): void
    {
        $low = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $low->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 2.5,
            'rating_count' => 5,
        ]);

        $high = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $high->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 4.9,
            'rating_count' => 30,
        ]);

        $response = $this->getJson('/api/search/providers?min_rating=4');
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($high->id, $ids);
        $this->assertNotContains($low->id, $ids);
    }

    public function test_services_search_returns_active_only(): void
    {
        $trade = Trade::create([
            'name' => 'Search test trade',
            'slug' => 'search-test-'.uniqid(),
            'code' => 'STT'.substr(uniqid(), -6),
            'is_active' => true,
        ]);
        ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => true, 'name' => 'Active service']);
        ServiceCatalog::factory()->create(['trade_id' => $trade->id, 'is_active' => false, 'name' => 'Inactive service']);

        $response = $this->getJson('/api/search/services');
        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Active service', $names);
        $this->assertNotContains('Inactive service', $names);
    }

    public function test_postal_autocomplete_returns_matches(): void
    {
        PostalCode::factory()->create(['code' => '1000', 'city_name' => 'Bruxelles', 'is_active' => true]);
        PostalCode::factory()->create(['code' => '1050', 'city_name' => 'Ixelles', 'is_active' => true]);

        $response = $this->getJson('/api/search/postal-autocomplete?q=10');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_postal_autocomplete_validates_min_length(): void
    {
        $response = $this->getJson('/api/search/postal-autocomplete?q=a');
        $response->assertStatus(422);
    }
}
