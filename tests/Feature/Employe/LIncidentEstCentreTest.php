<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\SignalerIncident;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le heros s'etalait sur toute la page pendant que le formulaire, bride a `max-w-4xl` SANS
 * `mx-auto`, restait colle a gauche : 1216 px contre 896 px, et 352 px de vide a droite.
 */
class LIncidentEstCentreTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        $utilisateur = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $utilisateur->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $utilisateur->fresh();
    }

    public function test_la_page_porte_un_conteneur_centre(): void
    {
        Livewire::actingAs($this->prestataire())->test(SignalerIncident::class)
            ->assertOk()
            ->assertSee('mx-auto w-full max-w-4xl', escape: false);
    }

    /**
     * TEMOIN — le formulaire ne porte plus SA largeur : sans ce controle, un `max-w-4xl` reste
     * a gauche a l'interieur du conteneur centre, et le centrage ne se verrait pas.
     */
    public function test_temoin_le_formulaire_ne_bride_plus_sa_propre_largeur(): void
    {
        $rendu = Livewire::actingAs($this->prestataire())->test(SignalerIncident::class)->html();

        $this->assertStringNotContainsString('max-w-4xl rounded-[2rem]', $rendu,
            'Le formulaire garde sa contrainte propre : il resterait colle a gauche.');
    }
}
