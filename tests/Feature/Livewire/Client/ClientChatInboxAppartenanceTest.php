<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\ClientChatInbox;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `selectThread()` vérifie l'appartenance au fil — mais la LECTURE des
 * messages ne la reposait pas. Or la propriété qui désigne le fil actif est
 * pilotable depuis le navigateur : la garde de l'aiguillage ne protège rien
 * si l'affichage se sert directement.
 *
 * Le témoin positif est indispensable : sans lui, un composant qui ne rend
 * jamais aucun message ferait passer le test de refus au vert.
 */
class ClientChatInboxAppartenanceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: ChatThread, 1: ChatMessage} */
    private function filAvecMessage(User $membre, string $texte): array
    {
        $fil = ChatThread::factory()->create(['is_archived' => false]);
        ChatParticipant::factory()->create([
            'thread_id' => $fil->id,
            'user_id' => $membre->id,
            'left_at' => null,
        ]);
        $message = ChatMessage::factory()->create([
            'thread_id' => $fil->id,
            'sender_user_id' => $membre->id,
            'body' => $texte,
            'is_deleted' => false,
        ]);

        return [$fil, $message];
    }

    /** TÉMOIN POSITIF — le participant lit bien les messages de son fil. */
    public function test_le_participant_lit_son_fil(): void
    {
        $membre = User::factory()->create();
        [$fil] = $this->filAvecMessage($membre, 'Message du temoin positif');

        Livewire::actingAs($membre)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $fil->id)
            ->assertSet('activeThreadId', $fil->id)
            ->assertSee('Message du temoin positif');
    }

    /** REFUS — un tiers ne lit pas le fil d'autrui. */
    public function test_un_tiers_ne_lit_pas_le_fil_d_autrui(): void
    {
        $membre = User::factory()->create();
        $curieux = User::factory()->create();
        [$fil] = $this->filAvecMessage($membre, 'Conversation privee confidentielle');

        Livewire::actingAs($curieux)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $fil->id)
            ->assertDontSee('Conversation privee confidentielle');
    }

    /** La propriété ne doit pas être pilotable depuis le navigateur. */
    public function test_le_fil_actif_est_verrouille(): void
    {
        $membre = User::factory()->create();
        $curieux = User::factory()->create();
        [$fil] = $this->filAvecMessage($membre, 'Secret bien garde');

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($curieux)
            ->test(ClientChatInbox::class)
            ->set('activeThreadId', $fil->id);
    }

    /**
     * Quitter un fil doit fermer la lecture — le cas qui prouve que la garde
     * est bien reposée à l'affichage et pas seulement à l'aiguillage.
     */
    public function test_celui_qui_a_quitte_le_fil_ne_lit_plus(): void
    {
        $membre = User::factory()->create();
        [$fil] = $this->filAvecMessage($membre, 'Message avant le depart');

        $composant = Livewire::actingAs($membre)
            ->test(ClientChatInbox::class)
            ->call('selectThread', $fil->id)
            ->assertSee('Message avant le depart');

        ChatParticipant::query()
            ->where('thread_id', $fil->id)
            ->where('user_id', $membre->id)
            ->update(['left_at' => now()]);

        $composant->call('refresh')->assertDontSee('Message avant le depart');
    }
}
