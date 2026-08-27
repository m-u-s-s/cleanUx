<?php

namespace Tests\Feature\Messaging;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * LA PIÈCE JOINTE D'UNE DISCUSSION N'ÉTAIT PAS OUVRABLE DEPUIS UN TÉLÉPHONE.
 *
 * `messaging.attachments.download` est gardée par une session WEB. Une balise `audio` ou `Image`
 * d'un appareil n'en a pas, et n'obtenait qu'une redirection vers la connexion.
 *
 * MAIS CETTE ROUTE, ELLE, AUTORISE VRAIMENT : elle vérifie l'appartenance au canal. Lui retirer
 * l'authentification aurait cassé sa propre garde — le correctif n'était donc PAS d'une ligne.
 * Le lecteur est nommé DANS l'URL, la signature l'atteste, et la vérification ne change pas.
 */
class LaPieceJointeSOuvreDepuisUnAppareilTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: MessageAttachment} */
    private function pieceDansUnCanal(): array
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('attachments', UploadedFile::fake()->create('note.pdf', 4), 'note.pdf');

        $membre = User::factory()->create();
        $canal = Channel::factory()->create();
        $canal->members()->attach($membre->id, ['role' => 'member']);

        $message = Message::factory()->create(['channel_id' => $canal->id, 'user_id' => $membre->id]);

        $piece = MessageAttachment::factory()->create([
            'message_id' => $message->id,
            'uploaded_by' => $membre->id,
            'disk' => 'public',
            'path' => 'attachments/note.pdf',
            'av_status' => MessageAttachment::AV_STATUS_CLEAN,
        ]);

        return [$membre, $piece];
    }

    public function test_un_membre_ouvre_sa_piece_sans_aucune_session(): void
    {
        [$membre, $piece] = $this->pieceDansUnCanal();

        $this->get((string) $piece->urlPourAppareil($membre->id))->assertOk();
    }

    /**
     * LE POINT QUI COMPTE. L'autorisation n'a pas été échangée contre du confort : un lien
     * parfaitement signé, nommant quelqu'un qui n'est pas du canal, est refusé.
     */
    public function test_un_lien_signe_pour_un_etranger_est_refuse(): void
    {
        [, $piece] = $this->pieceDansUnCanal();

        $etranger = User::factory()->create();

        $this->get((string) $piece->urlPourAppareil($etranger->id))->assertForbidden();
    }

    /** Substituer le lecteur invalide la signature : elle couvre la requête entière. */
    public function test_changer_le_lecteur_invalide_le_lien(): void
    {
        [$membre, $piece] = $this->pieceDansUnCanal();

        $etranger = User::factory()->create();

        $detourne = str_replace(
            'viewer='.$membre->id,
            'viewer='.$etranger->id,
            (string) $piece->urlPourAppareil($membre->id)
        );

        $this->get($detourne)->assertForbidden();
    }

    /** LE TÉMOIN : la porte du web garde son verrou de session, et fonctionne avec. */
    public function test_temoin_le_lien_web_exige_toujours_une_session(): void
    {
        [$membre, $piece] = $this->pieceDansUnCanal();

        $this->get((string) $piece->signed_url)->assertRedirect(route('login'));

        $this->actingAs($membre)->get((string) $piece->signed_url)->assertOk();
    }

    /** Un lien sans lecteur nommé et sans session ne vaut rien. */
    public function test_sans_lecteur_ni_session_l_acces_est_refuse(): void
    {
        [, $piece] = $this->pieceDansUnCanal();

        $sansLecteur = URL::temporarySignedRoute('messaging.attachments.device', now()->addMinutes(15), [
            'attachment' => $piece->id,
        ]);

        $this->get($sansLecteur)->assertUnauthorized();
    }
}
