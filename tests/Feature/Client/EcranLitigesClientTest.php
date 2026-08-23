<?php

namespace Tests\Feature\Client;

use App\Livewire\Client\LitigesClient;
use App\Models\CustomerClaim;
use App\Models\CustomerClaimEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** L'ÉCRAN DES LITIGES CLIENT, DE BOUT EN BOUT. */
class EcranLitigesClientTest extends TestCase
{
    use RefreshDatabase;

    private function reclamationDe(User $client, string $titre = 'Vitre mal nettoyée'): CustomerClaim
    {
        return CustomerClaim::create([
            'client_id' => $client->id,
            'category' => 'quality',
            'priority' => 'normal',
            'status' => 'open',
            'title' => $titre,
            'description' => 'La vitre du salon est restée sale après le passage.',
        ]);
    }

    /** TÉMOIN — déposer une réclamation la fait apparaître dans sa propre liste. */
    public function test_une_reclamation_deposee_apparait_dans_la_liste(): void
    {
        $client = User::factory()->client()->create();

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->set('title', 'Retard de deux heures')
            ->set('description', 'Le prestataire est arrivé avec deux heures de retard.')
            ->set('category', 'quality')
            ->set('priority', 'normal')
            ->call('createClaim')
            ->assertSee('Retard de deux heures');

        $this->assertSame(1, CustomerClaim::where('client_id', $client->id)->count());
    }

    /** TÉMOIN — sélectionner une réclamation ouvre son détail. */
    public function test_selectionner_ouvre_le_detail(): void
    {
        $client = User::factory()->client()->create();
        $claim = $this->reclamationDe($client);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->assertSet('selectedId', $claim->id)
            ->assertSee('Vitre mal nettoyée')
            ->assertSee('REC-'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT));
    }

    /** REFUS — on n'ouvre pas la réclamation d'autrui en changeant un nombre. */
    public function test_la_reclamation_d_autrui_ne_s_ouvre_pas(): void
    {
        $victime = User::factory()->client()->create();
        $curieux = User::factory()->client()->create();
        $claim = $this->reclamationDe($victime, 'Litige confidentiel du voisin');

        Livewire::actingAs($curieux)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->assertSet('selectedId', null)
            ->assertDontSee('Litige confidentiel du voisin');
    }

    /** TÉMOIN — répondre ajoute un message au fil, et le fil s'affiche. */
    public function test_repondre_ajoute_un_message_au_fil(): void
    {
        $client = User::factory()->client()->create();
        $claim = $this->reclamationDe($client);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->set('replyBody', 'Je vous envoie une photo de la vitre.')
            ->call('postReply')
            ->assertSet('replyBody', '')
            ->assertSee('Je vous envoie une photo de la vitre.');

        $this->assertSame(1, CustomerClaimEvent::where('customer_claim_id', $claim->id)->count());
        $this->assertSame('client', CustomerClaimEvent::firstOrFail()->author_role);
    }

    /** Une réponse vide est refusée — le formulaire le dit plutôt que d'écrire du blanc. */
    public function test_une_reponse_vide_est_refusee(): void
    {
        $client = User::factory()->client()->create();
        $claim = $this->reclamationDe($client);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->set('replyBody', '')
            ->call('postReply')
            ->assertHasErrors('replyBody');

        $this->assertSame(0, CustomerClaimEvent::count());
    }

    /** REFUS — une réclamation clôturée n'accepte plus de réponse. */
    public function test_une_reclamation_cloturee_n_accepte_plus_de_reponse(): void
    {
        $client = User::factory()->client()->create();
        $claim = $this->reclamationDe($client);
        $claim->update(['status' => 'closed']);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->set('replyBody', 'Une dernière chose…')
            ->call('postReply');

        $this->assertSame(0, CustomerClaimEvent::count(), 'Une réclamation close ne se rouvre pas par une réponse');
    }

    /** La résolution s'affiche sous la forme que la vue attend. */
    public function test_la_resolution_s_affiche(): void
    {
        $client = User::factory()->client()->create();
        $claim = $this->reclamationDe($client);
        $claim->update([
            'status' => 'resolved',
            'resolution' => 'Un second passage a été offert.',
            'resolved_at' => now(),
        ]);

        Livewire::actingAs($client)
            ->test(LitigesClient::class)
            ->call('select', $claim->id)
            ->assertSee('Un second passage a été offert.');
    }
}
