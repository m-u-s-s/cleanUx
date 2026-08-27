<?php

namespace Tests\Feature\ChatV2;

use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La liste des messages porte l'adresse de la pièce jointe, ouvrable sans jeton, et le nom de
 * l'expéditeur ; l'appartenance au fil est vérifiée sur le lecteur que nomme l'URL signée.
 */
class LaPieceJointeDuFilSAfficheSurAppareilTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ChatThread, 2: ChatMessage} */
    private function filAvecUnePiece(): array
    {
        Config::set('chat_v2.attachments_disk', 'local');
        Storage::fake('local');
        Storage::disk('local')->put('chat/photo.jpg', 'des octets');

        $membre = User::factory()->create();
        $fil = ChatThread::factory()->create();

        ChatParticipant::factory()->create([
            'thread_id' => $fil->id,
            'user_id' => $membre->id,
            'role' => ChatParticipant::ROLE_CLIENT,
        ]);

        $message = ChatMessage::factory()->create([
            'thread_id' => $fil->id,
            'sender_user_id' => $membre->id,
            'attachment_path' => 'chat/photo.jpg',
            'attachment_mime' => 'image/jpeg',
            'attachment_size_bytes' => 10,
        ]);

        return [$membre, $fil, $message];
    }

    public function test_la_liste_donne_une_adresse_ouvrable_pour_la_piece(): void
    {
        [$membre, $fil, $message] = $this->filAvecUnePiece();

        Sanctum::actingAs($membre);

        $piece = $this->getJson("/api/v2/chat/threads/{$fil->id}/messages")
            ->assertOk()
            ->json('data.0.attachment');

        $this->assertNotNull($piece, 'La liste ne porte aucun descripteur de pièce jointe.');
        $this->assertSame('image/jpeg', $piece['mime_type']);
        $this->assertSame(10, $piece['size_bytes']);
        $this->assertStringContainsString("/api/v2/chat/messages/{$message->id}/attachment/appareil", $piece['url']);

        $this->get($piece['url'])->assertOk();
    }

    /** LE TÉMOIN : un message sans pièce n'en invente pas une. */
    public function test_temoin_un_message_sans_piece_ne_porte_pas_de_descripteur(): void
    {
        [$membre, $fil] = $this->filAvecUnePiece();

        ChatMessage::factory()->create([
            'thread_id' => $fil->id,
            'sender_user_id' => $membre->id,
            'attachment_path' => null,
        ]);

        Sanctum::actingAs($membre);

        $this->getJson("/api/v2/chat/threads/{$fil->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.attachment', null);
    }

    /** Les deux clés dont les écrans se servent pour poser la bulle du bon côté, avec un nom. */
    public function test_la_liste_nomme_l_expediteur(): void
    {
        [$membre, $fil] = $this->filAvecUnePiece();

        Sanctum::actingAs($membre);

        $this->getJson("/api/v2/chat/threads/{$fil->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.sender_id', $membre->id)
            ->assertJsonPath('data.0.sender_name', $membre->name);
    }

    /** Un lien parfaitement signé, nommant quelqu'un qui n'est pas du fil, reste refusé. */
    public function test_un_lien_signe_pour_un_etranger_est_refuse(): void
    {
        [, , $message] = $this->filAvecUnePiece();

        $etranger = User::factory()->create();

        $this->get(URL::temporarySignedRoute('chat-v2.attachment.device', now()->addMinutes(15), [
            'message' => $message->id,
            'viewer' => $etranger->id,
        ]))->assertForbidden();
    }

    /** Substituer le lecteur invalide la signature : elle couvre la requête entière. */
    public function test_changer_le_lecteur_invalide_le_lien(): void
    {
        [$membre, , $message] = $this->filAvecUnePiece();

        $etranger = User::factory()->create();

        $lien = URL::temporarySignedRoute('chat-v2.attachment.device', now()->addMinutes(15), [
            'message' => $message->id,
            'viewer' => $membre->id,
        ]);

        $this->get(str_replace('viewer='.$membre->id, 'viewer='.$etranger->id, $lien))->assertForbidden();
    }

    /** Sans lecteur nommé et sans jeton, le lien signé ne vaut rien. */
    public function test_sans_lecteur_ni_jeton_l_acces_est_refuse(): void
    {
        [, , $message] = $this->filAvecUnePiece();

        $this->get(URL::temporarySignedRoute('chat-v2.attachment.device', now()->addMinutes(15), [
            'message' => $message->id,
        ]))->assertForbidden();
    }

    /** LE TÉMOIN DE LA PORTE : le point authentifié fonctionne toujours, jeton en main. */
    public function test_temoin_le_point_authentifie_sert_toujours_la_piece(): void
    {
        [$membre, , $message] = $this->filAvecUnePiece();

        Sanctum::actingAs($membre);

        $this->get("/api/v2/chat/messages/{$message->id}/attachment")->assertOk();
    }
}
