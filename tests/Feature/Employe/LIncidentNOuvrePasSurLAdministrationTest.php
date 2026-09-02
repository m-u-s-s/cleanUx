<?php

namespace Tests\Feature\Employe;

use App\Livewire\Admin\ProductEmailsCenter;
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
     * TEMOIN — le bandeau n'est pas supprime du produit : il reste sur les pages
     * d'administration qui l'incluent — le centre d'audit ne l'inclut plus depuis qu'il ouvre
     * sur son propre titre. Sans ce controle, le premier test resterait vert
     * meme si le partiel avait ete vide de son contenu.
     */
    public function test_temoin_les_pages_d_administration_le_gardent(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(ProductEmailsCenter::class)
            ->assertSee('Centre de communication');
    }
}
