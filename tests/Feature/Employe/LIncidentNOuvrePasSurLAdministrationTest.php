<?php

namespace Tests\Feature\Employe;

use App\Livewire\Employe\SignalerIncident;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Le bandeau « Centre de communication & suivi qualite » est du contenu d'ADMINISTRATION.
 * Il precedait le formulaire d'un prestataire qui signale un incident.
 */
class LIncidentNOuvrePasSurLAdministrationTest extends TestCase
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

    public function test_la_page_d_incident_n_ouvre_plus_sur_le_bandeau(): void
    {
        Livewire::actingAs($this->prestataire())->test(SignalerIncident::class)
            ->assertOk()
            ->assertDontSee('Centre de communication');
    }

    /** TEMOIN — le formulaire d'incident, lui, est toujours la. */
    public function test_temoin_le_formulaire_d_incident_reste(): void
    {
        Livewire::actingAs($this->prestataire())->test(SignalerIncident::class)
            ->assertSee('incident', escape: false);
    }

    /**
     * TEMOIN — le bandeau n'est pas supprime du produit, il rend toujours sa phrase.
     *
     * Il n'a plus de page d'ADMINISTRATION porteuse : le centre d'audit l'a quitte, et le centre
     * e-mails est devenu un atelier. Le temoin ne peut donc plus etre une page — c'est le gabarit
     * lui-meme qui prouve que la phrase cherchee au test precedent est bien la sienne.
     */
    public function test_temoin_le_gabarit_rend_toujours_sa_phrase(): void
    {
        $this->assertStringContainsString(
            'Centre de communication',
            view('livewire.shared.communication.hero')->render(),
        );
    }
}
