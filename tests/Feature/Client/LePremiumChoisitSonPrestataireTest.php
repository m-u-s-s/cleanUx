<?php

namespace Tests\Feature\Client;

use App\Enums\OrganizationType;
use App\Livewire\Client\BrowseCompanies;
use App\Livewire\Client\BrowseProviders;
use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'annuaire savait deja emettre un choix de prestataire — personne ne l'ecoutait, et aucune
 * page ne le montait. Le client premium choisit desormais depuis l'annuaire.
 */
class LePremiumChoisitSonPrestataireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 200)]);
    }

    /** L'annuaire ne liste qu'un profil ACTIF et VERIFIE : sans lui, aucune carte ne sort. */
    private function prestataire(): User
    {
        $employe = User::factory()->employe()->create();

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

    public function test_un_client_premium_voit_les_deux_gestes(): void
    {
        $this->prestataire();

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->test(BrowseProviders::class)
            ->assertOk()
            ->assertSee('Réserver avec')
            ->assertSee('Ajouter à mes préférés');
    }

    /**
     * TEMOIN — un client standard voit la page ET l'invitation. Sans ce controle, le test
     * ci-dessus resterait vert si l'annuaire ne rendait plus aucune carte.
     */
    public function test_temoin_un_client_standard_voit_l_annuaire_et_l_invitation(): void
    {
        $employe = $this->prestataire();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->assertOk()
            ->assertSee($employe->name)
            ->assertDontSee('Réserver avec')
            ->assertSee('Premium');
    }

    /** Une methode Livewire est une porte HTTP : la garde ne vit pas dans la vue. */
    public function test_un_client_standard_ne_peut_pas_forcer_le_choix(): void
    {
        $employe = $this->prestataire();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->call('reserverAvec', $employe->id)
            ->assertForbidden();
    }

    public function test_reserver_avec_mene_au_parcours_avec_le_prestataire(): void
    {
        $employe = $this->prestataire();

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->test(BrowseProviders::class)
            ->call('reserverAvec', $employe->id)
            ->assertRedirect(route('client.rendezvous.create', ['prestataire' => $employe->id]));
    }

    public function test_le_prefere_s_ajoute_et_se_retire(): void
    {
        $employe = $this->prestataire();
        $client = User::factory()->premiumClient()->create();

        $composant = Livewire::actingAs($client)->test(BrowseProviders::class)
            ->call('basculerPrefere', $employe->id);

        $this->assertTrue($client->fresh()->favoriteEmployes()->where('users.id', $employe->id)->exists());

        $composant->call('basculerPrefere', $employe->id);

        $this->assertFalse($client->fresh()->favoriteEmployes()->where('users.id', $employe->id)->exists());
    }

    /** Un client standard ne peut pas non plus se fabriquer un prefere. */
    public function test_temoin_un_client_standard_ne_peut_pas_ajouter_un_prefere(): void
    {
        $employe = $this->prestataire();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseProviders::class)
            ->call('basculerPrefere', $employe->id)
            ->assertForbidden();
    }

    /** L'annuaire des societes ne liste que celles qui ont une note. */
    private function societePrestataire(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'rating_avg' => 4.6,
        ]);
    }

    /** Les societes : le meme geste, sans favori — aucune table ne le porte. */
    public function test_la_societe_se_reserve_aussi_depuis_l_annuaire(): void
    {
        $societe = $this->societePrestataire();

        Livewire::actingAs(User::factory()->premiumClient()->create())
            ->test(BrowseCompanies::class)
            ->assertOk()
            ->assertSee($societe->name)
            ->assertSee('Réserver avec cette société');
    }

    /** TEMOIN — un client standard voit la societe, mais pas le bouton. */
    public function test_temoin_la_societe_reste_visible_sans_le_bouton(): void
    {
        $societe = $this->societePrestataire();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseCompanies::class)
            ->assertSee($societe->name)
            ->assertDontSee('Réserver avec cette société')
            ->assertSee('Premium');
    }

    public function test_un_client_standard_ne_peut_pas_forcer_le_choix_d_une_societe(): void
    {
        $societe = $this->societePrestataire();

        Livewire::actingAs(User::factory()->client()->create())
            ->test(BrowseCompanies::class)
            ->call('reserverAvecLaSociete', $societe->id)
            ->assertForbidden();
    }
}
