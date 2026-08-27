<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\LitigesClient;
use App\Models\CustomerClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE CLIENT JOIGNAIT DES PREUVES QU'IL NE REVOYAIT JAMAIS.
 *
 * `LitigesClient::createClaim()` stocke les photos sur le disque PRIVÉ et les écrit dans
 * `customer_claims.attachments` depuis toujours. Aucun écran ne les rendait : l'affichage existait
 * — `livewire/client/litiges/claim-attachments` — mais son seul appelant était `claim-card`, une
 * vue qu'aucune route, aucun composant et aucune navigation n'atteignaient.
 *
 * Un test de sécurité (`PrivateMediaTest`) gardait donc la bonne propriété d'un partiel que
 * personne ne voyait. Il est maintenant inclus par l'écran routé.
 */
class LesPreuvesDUnLitigeSeVoientTest extends TestCase
{
    use RefreshDatabase;

    private function litigeAvecPreuve(User $client): CustomerClaim
    {
        return CustomerClaim::factory()->create([
            'client_id' => $client->id,
            'attachments' => [
                ['path' => 'claims/preuve.jpg', 'original_name' => 'preuve.jpg'],
            ],
        ]);
    }

    public function test_les_preuves_apparaissent_sur_l_ecran_du_client(): void
    {
        $client = User::factory()->client()->create();
        $litige = $this->litigeAvecPreuve($client);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $litige->id)
            ->assertSee('Preuves ajoutées');
    }

    /** ET PAR UNE URL SIGNÉE, jamais par le lien public — c'est la propriété que le test de média garde. */
    public function test_elles_passent_par_la_route_privee_signee(): void
    {
        $client = User::factory()->client()->create();
        $litige = $this->litigeAvecPreuve($client);

        $html = Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $litige->id)
            ->html();

        $this->assertStringContainsString('media/private', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertStringNotContainsString('/storage/claims', $html);
    }

    /**
     * LE TÉMOIN. Sans lui, les deux cas passeraient au vert si l'écran affichait toujours le bloc
     * de preuves : on ne mesurerait plus les pièces jointes, mais un gabarit.
     */
    public function test_temoin_un_litige_sans_preuve_n_affiche_pas_le_bloc(): void
    {
        $client = User::factory()->client()->create();

        $litige = CustomerClaim::factory()->create([
            'client_id' => $client->id,
            'attachments' => null,
        ]);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $litige->id)
            ->assertDontSee('Preuves ajoutées');
    }
}
